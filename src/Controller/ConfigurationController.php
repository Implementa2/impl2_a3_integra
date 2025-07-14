<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigurationController extends FrameworkBundleAdminController
{
    /**
     * Muestra y procesa el formulario de configuración.
     */
    public function index(Request $request): Response
    {
        // Obtiene el handler del formulario registrado en services.yml
        $formHandler = $this->get('prestashop.module.impl2_a3_integra.form.data_handler');
        $form = $formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Guarda los datos a través del DataProvider
            $errors = $formHandler->save($form->getData());
            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Configuración guardada correctamente.', 'Modules.Impl2A3Integra.Admin'));
                // Redirige a la misma página para evitar reenvío de POST
                return $this->redirectToRoute('impl2_a3_integra_config');
            }
            // Muestra los errores (si los hay)
            $this->flashErrors($errors);
        }

        return $this->render('@Modules/impl2_a3_integra/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
        ]);
    }
}
