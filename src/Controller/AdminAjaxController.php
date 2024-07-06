<?php

namespace App\Controller;

use App\Constants;
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
use Symfony\Component\HttpFoundation\RequestStack;
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
        if($user->getId() >= 2) return new JsonResponse(["message" => "Fehler, keine Berechtigungen"], 403);
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
        if($user->getId() >= 2) return new JsonResponse(["message" => "Fehler, keine Berechtigungen"], 403);
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
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
        if($creator->getLives() <= 0) return new JsonResponse(["message" => "Fehler, keine Leben mehr"], 400);
        $creator->setLives($creator->getLives() - 1);

        $entityManager->persist($creator);
        $entityManager->flush();

        $lives = $creator->getLives();
        $old_lives = $lives + 1;
        $r = [];
        $r["old_lives"] = $old_lives;
        $r["new_lives"] = $lives;
        $r["error"] = false;
        $r["message"] = "Success, removed live for '{$creator->getName()}' ({$old_lives} -> {$lives})";
        return new JsonResponse($r, 200);
    }

    #[Route('/ajax/{name}/add_live', name: 'app_ajax_add_live')]
    public function app_ajax_add_live(#[MapEntity(mapping: ['name'])] Creator $creator, EntityManagerInterface $entityManager, UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
        if($creator->getLives() >= Constants::$MAX_LIVES) return new JsonResponse(["message" => "Fehler, maximale Leben erreicht"], 400);
        $creator->setLives($creator->getLives() + 1);

        $entityManager->persist($creator);
        $entityManager->flush();

        $lives = $creator->getLives();
        $old_lives = $lives - 1;
        $r = [];
        $r["old_lives"] = $old_lives;
        $r["new_lives"] = $lives;
        $r["error"] = false;
        $r["message"] = "Success, added live for '{$creator->getName()}' ({$old_lives} -> {$lives})";
        return new JsonResponse($r, 200);
    }

    #[Route('/ajax/next_question/{ignore_used}', name: 'app_ajax_next_question', requirements: ['ignore_used' => '^(true|false|1|0)$'])]
    public function app_ajax_next_question(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager, string $ignore_used='false'): Response
    {
        $ignore_used = $ignore_used == 'true' || $ignore_used == '1';
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
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
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
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
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
        $question = $entityManager->getRepository(Question::class)->find($id);
        if($question == null) return new JsonResponse(["message" => "Fehler, Frage nicht gefunden"], 404);
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
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
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

    #[Route('/ajax/add_question', name: 'app_ajax_add_question', methods: ['POST'])]
    public function app_ajax_add_question(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
        if(!isset($_POST["question"]) || !isset($_POST["answer"])) return new JsonResponse(["message" => "Fehler, fehlender Fragentext oder Antwort"], 400);
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

    // Search question
    #[Route('/ajax/search_question', name: 'app_ajax_search_question', methods: ['GET'])]
    public function app_ajax_search_question(UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);
        $text = $request->query->get("text");
        $answer = $request->query->get("answer");
        $id = $request->query->get("id");
        if($text == null && $answer == null && $id == null) return new JsonResponse(["message" => "Fehler, fehlende Suchkriterien", "error" => true], 400);
        $qb = $entityManager->getRepository(Question::class)->createQueryBuilder('q');
        if($text) $qb->andWhere('q.text LIKE :text')->setParameter('text', "%$text%");
        if($answer) $qb->andWhere('q.answer LIKE :answer')->setParameter('answer', "%$answer%");
        if($id) $qb->andWhere('q.id = :id')->setParameter('id', $id);
        // Set Limit
        $qb->setMaxResults(50);
        $questions = $qb->getQuery()->getResult();
        if (count($questions) == 0) return new JsonResponse(["message" => "Fehler, keine Fragen gefunden", "error" => true], 404);
        $r = [];
        $r["error"] = false;
        $r["message"] = "Success, found " . count($questions) . " questions";
        $r["questions"] = [];
        foreach($questions as $question) {
            $r["questions"][] = [
                'id' => $question->getId(),
                'text' => $question->getText(),
                'answer' => $question->getAnswer()
            ];
        }
        return new JsonResponse($r, 200);
    }


    #[Route('/ajax/timer/{action}', name: 'app_ajax_timer_action')]
    public function app_ajax_start_timer(string $action, EntityManagerInterface $entityManager, Request $request, UserValidatorService $userValidatorService): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if ($user instanceof Response) return $user;
        if ($user->getId() >= 2) return new Response("Fehler, keine Berechtigungen", 403);

        // Get current time
        $ending_at_optional = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_ending_at"]);
        if($ending_at_optional == null) {
            $ending_at = null;
            $paused_at = null;
            $timer_last_seconds = 180;

            $ending_at_optional = new GlobalSetting();
            $ending_at_optional->setName("timer_ending_at");
            $ending_at_optional->setValue($ending_at);
            $entityManager->persist($ending_at_optional);

            $paused_at_optional = new GlobalSetting();
            $paused_at_optional->setName("timer_paused_at");
            $paused_at_optional->setValue($paused_at);
            $entityManager->persist($paused_at_optional);

            $timer_last_seconds_optional = new GlobalSetting();
            $timer_last_seconds_optional->setName("timer_last_seconds");
            $timer_last_seconds_optional->setValue($timer_last_seconds);
            $entityManager->persist($timer_last_seconds_optional);

            $entityManager->flush();
        }
        else {
            $ending_at = $ending_at_optional->getValue();
            $paused_at = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_paused_at"])->getValue();
            $timer_last_seconds = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_last_seconds"])->getValue();
        }
        if ($ending_at == null) $ending_at = 0;
        $real_ending_at = $paused_at == null ? $ending_at : ($ending_at + (time() - $paused_at));

        switch ($action) {
            case "start":
                if(empty($_GET["seconds"]))
                    return new JsonResponse(["message" => "Fehler, keine Sekunden angegeben (GET:seconds)", "error" => true], 400);
                // if ($real_ending_at > time()) return new JsonResponse(["message" => "Fehler, Timer l&auml;uft bereits", "error" => true], 400);
                $seconds = intval($_GET["seconds"]);
                $ending_at = time() + $seconds;
                $paused_at = null;
                $timer_last_seconds = $seconds;
                $message = "Timer wurde für {$seconds} Sekunden gestartet";
                break;
            case "pause":
                // When pausing: 1. Pause
                if ($real_ending_at <= time()) return new JsonResponse(["message" => "Fehler, Timer l&auml;uft nicht", "error" => true], 400);
                if ($paused_at != null) return new JsonResponse(["message" => "Fehler, Timer bereits pausiert", "error" => true], 400);
                $paused_at = time();
                $message = "Timer wurde pausiert";
                break;
            case "resume":
                // When resuming: 1. Resume
                if ($paused_at == null) return new JsonResponse(["message" => "Fehler, Timer nicht pausiert", "error" => true], 400);
                $ending_at = $ending_at + (time() - $paused_at);
                $paused_at = null;
                $message = "Timer wurde fortgesetzt";
                break;
            case "reset":
                // When resetting: 1. Reset
                $ending_at = time() + $timer_last_seconds;
                $paused_at = time();
                $message = "Timer wurde zur&uuml;ckgesetzt";
                break;
            case "stop":
                // When stopping: 1. Stop
                $ending_at = null;
                $paused_at = null;
                $message = "Timer wurde gestoppt";
                break;
            default:
                return new JsonResponse(["message" => "Fehler, unbekannte Aktion (start;pause;reset;resume)", "error" => true], 400);
        }
        $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_ending_at"])->setValue($ending_at);
        $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_paused_at"])->setValue($paused_at);
        // For suggesting new start time
        $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_last_seconds"])->setValue($timer_last_seconds);
        $entityManager->flush();

        $r = [];
        $r["error"] = false;
        $r["message"] = $message;
        $r["ending_at"] = $ending_at;
        $r["paused_at"] = $paused_at;
        $r["paused"] = $paused_at != null;
        $r["timer_last_seconds"] = $timer_last_seconds;
        return new JsonResponse($r, 200);
    }

}