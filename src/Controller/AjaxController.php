<?php

namespace App\Controller;

use App\Constants;
use App\Entity\Creator;
use App\Entity\GlobalSetting;
use App\Services\UserValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AjaxController extends AbstractController
{

    #[Route('/ajax/validate', name: 'app_ajax_validate')]
    public function app_ajax_validate(UserValidatorService $userValidatorService): Response
    {
        $r = [];
        $r["error"] = true;
        $r["message"] = "Error, no auth";
        if(!isset($_POST["auth"])) return new JsonResponse($r, 403);
        $user = $userValidatorService->checkUser($_POST["auth"]);
        if($user instanceof Response) return $user;
        $r["error"] = false;
        $r["message"] = "Success, valid auth";
        $r["user_id"] = $user->getId();
        $r["user_name"] = $user->getName();
        $r['token'] = $user->getAuth();
        return new JsonResponse($r);
    }

    #[Route("/ajax/{name}/check_update/", name: 'app_ajax_check_update')]
    public function app_ajax_check_update(#[MapEntity(mapping: ['name'])] Creator $creator, EntityManagerInterface $entityManager): JsonResponse
    {
        $r = [];
        $r['id'] = $creator->getId();
        $r['lives'] = $creator->getLives();
        $r['name'] = $creator->getName();
        $r['votes'] = count($creator->getVotedBy());
        $show_votes = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "show_votes"]);
        $r['show_votes'] = $show_votes->getValue() == "1";
        if($creator->getVotedFor() != null) {
            $r['voted_for'] = [];
            $r['voted_for']['name'] = $creator->getVotedFor()->getName();
            $r['voted_for']['votes'] = count($creator->getVotedFor()->getVotedBy());
            $r['voted_for']['lives'] = $creator->getVotedFor()->getLives();
            $r['voted_for']['id'] = $creator->getVotedFor()->getId();
        } else {
            $r['voted_for'] = null;
        }

        return new JsonResponse($r);
    }

    #[Route("/ajax/{name}/lives_overlay", name: 'app_ajax_lives_overlay')]
    public function app_ajax_lives_overlay(#[MapEntity(mapping: ['name'])] Creator $creator): Response
    {
        return $this->render('lives.html.twig', [
            'creator' => $creator,
            'max_lives' => Constants::$MAX_LIVES
        ]);
    }

    #[Route("/ajax/{name}/votes_overlay", name: 'app_ajax_votes_overlay')]
    public function app_ajax_votes_overlay(#[MapEntity(mapping: ['name'])] Creator $creator, EntityManagerInterface $entityManager): Response
    {
        $show_votes = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "show_votes"]);
        return $this->render('votes.html.twig', [
            'creator' => $creator,
            'show_votes' => $show_votes->getValue() == "1"
        ]);
    }

    #[Route("/ajax/votes_overlay_admin", name: 'app_ajax_votes_overlay_admin')]
    public function app_ajax_votes_overlay_admin(EntityManagerInterface $entityManager): Response
    {
        return $this->render('votes_overlay_admin.html.twig', [
            'users' => $entityManager->getRepository(Creator::class)->findAll()
        ]);
    }

    #[Route('/ajax/{name}/vote', name: 'app_ajax_vote', methods: ["POST"])]
    public function app_ajax_vote(#[MapEntity(mapping: ['name'])] Creator $voted_for, EntityManagerInterface $entityManager, UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        # Check setting
        $allow_voting = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "allow_voting"]);
        if($allow_voting->getValue() == "0") return new JsonResponse(['message' => "Error, voting is disabled at the moment"], 400);
        if($user->getVotedFor() != null) return new JsonResponse(['message' => "Error, already voted"], 400);
        if($voted_for->getId() == $user->getId()) return new JsonResponse(['message' => "Error, cannot vote for self"], 400);
        if($voted_for->getId() <= 1) return new JsonResponse(['message' => "Error, cannot vote for this player"], 400);

        $user->setVotedFor($voted_for);

        $entityManager->persist($user);
        $entityManager->flush();

        $new_votes = count($voted_for->getVotedBy());
        $old_votes = $new_votes - 1;
        return new Response("Success, voted for '{$voted_for->getName()}' ({$old_votes} -> {$new_votes})");
    }

    #[Route('/ajax/timer-status', name: 'app_ajax_timer_status')]
    public function app_ajax_timer_status(EntityManagerInterface $entityManager): Response
    {
        $ending_at = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_ending_at"])->getValue();
        $paused_at = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_paused_at"])->getValue();
        $timer_last_seconds = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "timer_last_seconds"])->getValue();
        $real_ending_at = $paused_at == null ? $ending_at : ($ending_at + (time() - $paused_at));

        $r = [];
        $r['ending_at'] = (int)$real_ending_at;
        $r['paused'] = $paused_at != null;
        $r['paused_for'] = $paused_at != null ? time() - $paused_at : 0;
        $r['last_seconds'] = $timer_last_seconds;
        $r['current_time'] = time();

        return new JsonResponse($r);
    }


}