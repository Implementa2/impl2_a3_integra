<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ConfigurationType extends AbstractType
{
    /**
     * Construye el formulario con los campos de configuración.
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // URL base de la API de a3ERP
        $builder->add('api_url', TextType::class, [
            'label' => 'URL de la API a3ERP',
            'required' => true,
        ]);
        // Usuario de la API
        $builder->add('api_user', TextType::class, [
            'label' => 'Usuario API a3ERP',
            'required' => true,
        ]);
        // Contraseña/API Key (guardada cifrada)
        $builder->add('api_password', PasswordType::class, [
            'label' => 'Contraseña/API Key a3ERP',
            'required' => true,
        ]);
        // Tiendas a sincronizar (multi-select)
        $shops = [];
        foreach (\Shop::getShops(false) as $shop) {
            $shops[$shop['name']] = $shop['id_shop'];
        }
        $builder->add('shops', ChoiceType::class, [
            'label' => 'Tiendas a sincronizar',
            'choices' => $shops,
            'multiple' => true,
            'expanded' => false,
            'required' => true,
        ]);
        // Idiomas a sincronizar (multi-select)
        $langs = [];
        foreach (\Language::getLanguages() as $lang) {
            $langs[$lang['name']] = $lang['id_lang'];
        }
        $builder->add('languages', ChoiceType::class, [
            'label' => 'Idiomas a sincronizar',
            'choices' => $langs,
            'multiple' => true,
            'expanded' => false,
            'required' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'impl2_a3_integra_configuration';
    }
}
