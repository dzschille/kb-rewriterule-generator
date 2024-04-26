<?php

namespace App\Controller;

use App\Form\CsvType;
use App\Service\Csv;
use App\Service\RewriteRuleGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    #[Route('/', name: "app_home", methods: ['GET', 'POST'])]
    public function index(Request $request, RewriteRuleGenerator $rewriteRuleGenerator): Response
    {
        /**
         * initialize some variables
         */
        $form = $this->createForm(CsvType::class);
        $form->handleRequest($request);
        $errorMessages = $this->getParameter('errors');
        $error = false;
        $filename = false;
        $success = false;

        /**
         * upload CSV
         * check if file is valid CSV file
         *
         * parse CSV, add rewrite rules to generator
         * export txt file
         */
        if ($form->isSubmitted() && $form->isValid()) {
            $formOptions = $form->getData();
            /**
             * create new CSV instance from request
             * move CSV to tmp directory
             */
            $csv = new Csv($formOptions['csv_file']);

            if ($csv->isValid()) {

                $csv->moveToTmpDir($this->getParameter('csv_tmp_directory'));

                /**
                 * add rewriterules to generator
                 * set statuscode
                 * set rewriteengine
                 */
                $rewriteRuleGenerator->setRewriteRules($csv->getRewriteRules());

                if (isset($formOptions['options'])) {

                    if ($formOptions['options'] == 'custom_code') {
                        $rewriteRuleGenerator->setStatusCode((int) $formOptions['custom_status_code']);
                    } elseif ((int) $formOptions['options'] == 301) {
                        $rewriteRuleGenerator->setStatusCode(301);
                    }
                }


                /**
                 * check if RewriteEngine On shall be included
                 */
                if (isset($formOptions['rewrite_engine'])) {
                    $rewriteRuleGenerator->setRewriteEngineOn(true);
                }

                if (isset($formOptions['additional_flags'])) {
                    $rewriteRuleGenerator->setAdditionalFlags($formOptions['additional_flags']);
                }

                $rewriteRuleGenerator->setFileTemplate($this->renderView('rewrites.html.twig', ['csv' => $rewriteRuleGenerator->toArray()]));

                /**
                 * write txt file with given name and template
                 * returns download link
                 *
                 * finally remove CSV file from tmp folder
                 */
                $filename = $rewriteRuleGenerator->exportFile($this->getParameter('csv_upload_directory'), $this->buildNewFileName());

                $csv->removeTmpFile();
                $success = true;

                if ($csv->getInvalidUrls() > 0) {
                    $error = $csv->getInvalidUrls() . ' invalid urls were ignored.';
                }

                /**
                 * clear from after submission
                 */
                unset($form);
                $form = $this->createForm(CsvType::class);

            } else {
                $error = $errorMessages['invalid_extension'];
            }

        }

        return $this->render('index/index.html.twig', [
            'form' => $form,
            'error' => $error,
            'filename' => $filename,
            'success' => $success
        ]);

    }

    #[Route('/download/{filename}', name: "app_download")]
    public function download(string $filename): Response
    {
        $path = $this->getParameter('csv_upload_directory');
        $content = file_get_contents($path . '/' . $filename);

        $response = new Response();

        $response->headers->set('Content-type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');

        $response->setContent($content);
        return $response;
    }

    private function buildNewFileName(): string
    {
        $fileNameFormat = $this->getParameter('file_name_options');
        $date = new \DateTime('now');
        return sprintf("%s_%s.%s", $fileNameFormat['prefix'], $date->format($fileNameFormat['date_format']), $fileNameFormat['extension']);
    }
}
