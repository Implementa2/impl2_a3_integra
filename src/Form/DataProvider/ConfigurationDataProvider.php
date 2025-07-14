<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Form\DataProvider;

use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;
use Implementa2\A3Integra\Service\Encryptor;
use Configuration;

class ConfigurationDataProvider implements FormDataProviderInterface
{
    private Encryptor $encryptor;

    public function __construct(Encryptor $encryptor)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * Obtiene los valores actuales de configuración (antes de mostrar el formulario).
     */
    public function getData(): array
    {
        // Se descifran los valores o se toman vacíos si no existen
        $apiUrl = Configuration::get('A3ERP_API_URL');
        $apiUser = Configuration::get('A3ERP_API_USER');
        $apiPassEncrypted = Configuration::get('A3ERP_API_PASSWORD');
        
        $apiPass = $apiPassEncrypted ? $this->encryptor->decrypt($apiPassEncrypted) : '';

        $shopsJson = Configuration::get('A3ERP_SHOPS');
        $langsJson = Configuration::get('A3ERP_LANGUAGES');

        return [
            'api_url'       => $apiUrl ?: '',
            'api_user'      => $apiUser ?: '',
            'api_password'  => $apiPass,
            'shops'         => $shopsJson ? json_decode($shopsJson, true) : [],
            'languages'     => $langsJson ? json_decode($langsJson, true) : [],
        ];
    }

    /**
     * Guarda la configuración cuando el formulario es enviado.
     * Retorna array de errores si los hubiera (vacío si todo OK).
     */
    public function setData(array $data): array
    {
        try {
            Configuration::updateValue('A3ERP_API_URL', $data['api_url']);
            Configuration::updateValue('A3ERP_API_USER', $data['api_user']);
            // Cifrar la contraseña antes de guardar
            Configuration::updateValue('A3ERP_API_PASSWORD', $this->encryptor->encrypt($data['api_password']));
            
            // Guardar tiendas e idiomas como JSON
            Configuration::updateValue('A3ERP_SHOPS', json_encode($data['shops']));
            Configuration::updateValue('A3ERP_LANGUAGES', json_encode($data['languages']));
        } catch (\Exception $e) {
            return [(string)$e->getMessage()];
        }
        return [];
    }
}
