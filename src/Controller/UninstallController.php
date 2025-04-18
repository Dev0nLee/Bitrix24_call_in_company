<?php

namespace App\Controller;

use App\Repository\InstallationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UninstallController extends AbstractController
{
    #[Route('/uninstall', name: 'app_uninstall', methods: ['GET'])]
    public function uninstall(Request $request, InstallationRepository $installationRepository): Response
    {
        $domain = $request->query->get('domain');
        if (!$domain) {
            return new Response('Domain is required', 400);
        }

        $installationRepository->removeByDomain($domain);

        return new Response('Application uninstalled successfully', 200);
    }
}