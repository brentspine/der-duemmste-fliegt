<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OverlayController extends AbstractController
{

    #[Route("/{name}/overlay/{type}")]

    public function app_overlay($name, $type=null): Response
    {
        return new Response("Hello " . $name . " (" . $type . ")");
    }

}