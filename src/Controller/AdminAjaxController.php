<?php

namespace App\Controller;

use App\Entity\Creator;
use App\Entity\GlobalSetting;
use App\Entity\Question;
use App\Services\UserValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminAjaxController extends AbstractController
{

    #[Route('/ajax/change_setting/{name}/{val}', name: 'app_ajax_change_setting')]
    public function app_ajax_change_setting(string $name, string $val, UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new JsonResponse(["message" => "Error, not admin"], 403);
        $val = $val == "true" ? "1" : ($val == "false" ? "0" : $val);
        $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => $name])->setValue($val);
        $entityManager->flush();
        return new JsonResponse(["message" => "Success, changed setting {$name} to {$val}"]);
    }

    #[Route('/ajax/reset_votes', name: 'app_ajax_reset_votes')]
    public function app_ajax_reset_votes(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new JsonResponse(["message" => "Error, not admin"], 403);
        $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "show_votes"])->setValue("0");
        $entityManager->flush();

        # Set voted_for for each person to null
        $creators = $entityManager->getRepository(Creator::class)->findAll();
        foreach($creators as $creator) {
            $creator->setVotedFor(null);
            $entityManager->persist($creator);
        }
        $entityManager->flush();

        return new JsonResponse(["message" => "Success, resetted votes"]);
    }

    #[Route('/ajax/{name}/remove_live', name: 'app_ajax_remove_live')]
    public function app_ajax_remove_live(#[MapEntity(mapping: ['name'])] Creator $creator, EntityManagerInterface $entityManager, UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        $creator->setLives($creator->getLives() - 1);

        $entityManager->persist($creator);
        $entityManager->flush();

        $lives = $creator->getLives();
        $old_lives = $lives - 1;
        return new Response("Success, removed live for '{$creator->getName()}' ({$old_lives} -> {$lives})");
    }

    #[Route('/ajax/{name}/add_live', name: 'app_ajax_add_live')]
    public function app_ajax_add_live(#[MapEntity(mapping: ['name'])] Creator $creator, EntityManagerInterface $entityManager, UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        $creator->setLives($creator->getLives() + 1);

        $entityManager->persist($creator);
        $entityManager->flush();

        $lives = $creator->getLives();
        $old_lives = $lives - 1;
        return new Response("Success, added live for '{$creator->getName()}' ({$old_lives} -> {$lives})");
    }

    #[Route('/ajax/next_question/{ignore_used}', name: 'app_ajax_next_question', requirements: ['ignore_used' => '^(true|false|1|0)$'])]
    public function app_ajax_next_question(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager, string $ignore_used='false'): Response
    {
        $ignore_used = $ignore_used == 'true' || $ignore_used == '1';
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        # Find a random question
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Question::class, 'q');
        $rsm->addFieldResult('q', 'id', 'id');
        $rsm->addFieldResult('q', 'text', 'text');
        $rsm->addFieldResult('q', 'answer', 'answer');
        if(!$ignore_used) {
            $sql = "SELECT * FROM question WHERE used != 1 ORDER BY RAND() LIMIT 1";
        }
        else {
            $sql = "SELECT * FROM question ORDER BY RAND() LIMIT 1";
        }
        $question = $entityManager->createNativeQuery($sql, $rsm)
            ->getOneOrNullResult();

        if($question == null) return new JsonResponse(['id' => '-1', 'text' => "Fehler, keine Fragen &uuml;brig", 'answer' => 'Fragen zurücksetzen oder wiederverwenden'], 404);

        # Set global setting current question
        $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "current_question"])->setValue($question->getId());
        $entityManager->flush();

        // Mark the question as used
        $entityManager->createQueryBuilder()->update(Question::class, 'q')
            ->set('q.used', '1')
            ->where('q.id = :id')
            ->setParameter('id', $question->getId())
            ->getQuery()
            ->execute();

        $r = [];
        $r["id"] = $question->getId();
        $r["text"] = $question->getText();
        $r["answer"] = $question->getAnswer();
        return new JsonResponse($r, 200);
    }

    // Gets current question
    #[Route('/ajax/current_question', name: 'app_ajax_current_question')]
    public function app_ajax_current_question(EntityManagerInterface $entityManager): Response
    {
        $current_question = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "current_question"])->getValue();
        if($current_question == null) return new JsonResponse(['id' => '-1', 'text' => "Keine Frage ausgew&auml;lt", 'answer' => '...'], 404);
        $question = $entityManager->getRepository(Question::class)->find($current_question);
        $r = [];
        $r["id"] = $question->getId();
        $r["text"] = $question->getText();
        $r["answer"] = $question->getAnswer();
        return new JsonResponse($r, 200);
    }

    // Resets the used value of all questions to 0
    #[Route('/ajax/reset_questions', name: 'app_ajax_reset_questions')]
    public function app_ajax_reset_questions(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        $entityManager->createQueryBuilder()->update(Question::class, 'q')
            ->set('q.used', '0')
            ->getQuery()
            ->execute();
        return new JsonResponse(["message" => "Success, resetted questions"], 200);
    }

    // Resets question with id {id]
    #[Route('/ajax/reset_question/{id}', name: 'app_ajax_reset_question')]
    public function app_ajax_reset_question(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        $question = $entityManager->getRepository(Question::class)->find($id);
        if($question == null) return new JsonResponse(["message" => "Error, question not found"], 404);
        $question->setUsed(false);
        $entityManager->persist($question);
        $entityManager->flush();
        return new JsonResponse(["message" => "Success, resetted question with id {$id}"], 200);
    }

    // Gets all user objects
    #[Route('/ajax/get_users', name: 'app_ajax_get_users')]
    public function app_ajax_get_users(EntityManagerInterface $entityManager, UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        $users = $entityManager->getRepository(Creator::class)->findAll();
        $r = [];
        foreach($users as $user) {
            $r[] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'lives' => $user->getLives(),
                'votes' => count($user->getVotedBy()),
                'voted_for' => $user->getVotedFor() == null ? null : [
                    'id' => $user->getVotedFor()->getId(),
                    'name' => $user->getVotedFor()->getName(),
                    'lives' => $user->getVotedFor()->getLives(),
                    'votes' => count($user->getVotedFor()->getVotedBy())
                ]
            ];
        }
        return new JsonResponse($r, 200);
    }

    // Add question
    #[Route('/ajax/add_question', name: 'app_ajax_add_question', methods: ['POST'])]
    public function app_ajax_add_question(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Error, not admin", 403);
        if(!isset($_POST["question"]) || !isset($_POST["answer"])) return new JsonResponse(["message" => "Error, missing question or answer"], 400);
        $text = $_POST["question"];
        $answer = $_POST["answer"];
        if(strlen($text) <= 5 || strlen($answer) < 1) return new JsonResponse(["message" => "Bitte gebe eine l&auml;ngere Antwort oder Frage ein"], 400);
        $question = new Question();
        $question->setText($text);
        $question->setAnswer($answer);
        $entityManager->persist($question);
        $entityManager->flush();
        return new JsonResponse(["message" => "Success, added question"], 200);
    }

}