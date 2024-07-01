<?php

namespace App\Services;

use App\Entity\Creator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserValidatorService
{

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function checkUser(?string $auth) {
        if(!$auth) {
            return new JsonResponse(["error" => true, "message" => "Error, no auth provided"], 403);
        }
        $user = $this->entityManager->getRepository(Creator::class)->findOneBy(["auth" => $auth]);
        $r = [];
        $r["error"] = true;
        $r["message"] = "Error, invalid auth";
        if(!$user) return new JsonResponse($r, 403);
        return $user;
    }

}