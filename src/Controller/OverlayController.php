<?php

namespace App\Controller;

use App\Constants;
use App\Entity\Creator;
use App\Entity\GlobalSetting;
use App\Entity\Question;
use App\Services\UserValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OverlayController extends AbstractController
{

    #[Route("/{name}/overlay/", name: 'app_overlay')]
    public function app_overlay(#[MapEntity(mapping: ['name'])] Creator $creator, UserValidatorService $userValidatorService, Request $request, EntityManagerInterface $entityManager): Response
    {
        #$auth = $request->cookies->get("Authorization");
        #$user = $userValidatorService->checkUser($auth);
        #if($user instanceof Response) return $user;

        # Get the global setting "show_votes"
        $show_votes = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "show_votes"])->getValue() == "1";

        return $this->render('overlay.html.twig', [
            'width' => 500,
            'height' => 160,
            'creator' => $creator,
            'max_lives' => Constants::$MAX_LIVES,
            'debug' => "false",
            'show_votes' => $show_votes
        ]);
    }

    #[Route("/dashboard", name: 'app_dashboard')]
    public function app_dashboard(EntityManagerInterface $entityManager, UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if($user instanceof Response) return $user;
        if($user->getId() <= 1) {
            return $this->render("admin_dashboard.html.twig", [
                'allow_voting' => $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "allow_voting"])->getValue() == "1",
                'show_votes' => $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "show_votes"])->getValue() == "1",
                'users' => $entityManager->getRepository(Creator::class)->findAll(),
                'max_lives' => Constants::$MAX_LIVES,
            ]);
        }
        # Get all users
        $users = $entityManager->getRepository(Creator::class)->findAll();
        return $this->render('dashboard.html.twig', [
            'users' => $users,
            'creator' => $user
        ]);
    }

    #[Route('/', name: 'app_home')]
    public function home(UserValidatorService $userValidatorService, Request $request): Response
    {
        $auth = $request->cookies->get("Authorization");
        $user = $userValidatorService->checkUser($auth);
        if(!$user instanceof Response) {
            return $this->redirectToRoute('app_dashboard');
        }
        return $this->render('home.html.twig');
    }

    #[Route('/current-question', name: 'app_current_question')]
    public function app_current_question(EntityManagerInterface $entityManager): Response
    {
        $id = $entityManager->getRepository(GlobalSetting::class)->findOneBy(["name" => "current_question"])->getValue();
        /** @var Question $question */
        $question = $entityManager->createQueryBuilder()
            ->select('q')
            ->from(Question::class, 'q')
            ->where('q.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
        return $this->render('current_question.html.twig', [
            'question' => $question,
            'color' => empty($_GET["color"]) ? null : ($_GET['color'] == "standard" ? null : $_GET['color']),
            'font_family' => empty($_GET["font_family"]) ? null : ($_GET['font_family'] == "standard" ? null : $_GET['font_family']),
            'font_size' => empty($_GET["font_size"]) ? null : ($_GET['font_size'] == "standard" ? null : $_GET['font_size']),
            'font_weight' => empty($_GET["font_weight"]) ? null : ($_GET['font_weight'] == "standard" ? null : $_GET['font_weight']),
            'float' => empty($_GET["float"]) ? null : ($_GET['float'] == "standard" ? null : $_GET['float']),
        ]);
    }

}