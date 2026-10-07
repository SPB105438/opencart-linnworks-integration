<?php
namespace Opencart\Admin\Controller\Extension\Linnworks\Module;

class Linnworks extends \Opencart\System\Engine\Controller {
    private array $error = [];

    private array $setting_keys = [
        'status',
        'application_id',
        'application_secret',
        'token',
        'auth_url',
        'timeout',
        'public_base_url',
        'channel_name',
        'channel_friendly_name',
        'dry_run',
        'stock_location_id',
        'price_authority',
        'price_field'
    ];

    public function index(): void {
        $this->load->language('extension/linnworks/module/linnworks');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/linnworks/module/linnworks');

        if (($this->request->server['REQUEST_METHOD'] ?? '') === 'POST' && $this->validate()) {
            $settings = $this->request->post;
            $settings['module_linnworks_status'] = 1;
            $settings['module_linnworks_dry_run'] = 1;

            $this->model_setting_setting->editSetting('module_linnworks', $settings);
            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link(
                    'extension/linnworks/module/linnworks',
                    'user_token=' . $this->session->data['user_token'],
                    true
                )
            );
        }

        foreach ($this->setting_keys as $key) {
            $setting_name = 'module_linnworks_' . $key;
            $data[$setting_name] = $this->request->post[$setting_name] ?? $this->config->get($setting_name);
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['module_linnworks_auth_url'] = $data['module_linnworks_auth_url'] ?: 'https://api.linnworks.net/api/Auth/AuthorizeByApplication';
        $data['module_linnworks_timeout'] = $data['module_linnworks_timeout'] ?: 30;
        $data['module_linnworks_public_base_url'] = $data['module_linnworks_public_base_url'] ?: 'https://linnworks-gateway.spectrumbrands.com';
        $data['module_linnworks_channel_name'] = $data['module_linnworks_channel_name'] ?: 'GBAPPLIANCESOPENCART';
        $data['module_linnworks_channel_friendly_name'] = $data['module_linnworks_channel_friendly_name'] ?: 'GB Appliances OpenCart';
        $data['module_linnworks_price_authority'] = $data['module_linnworks_price_authority'] ?: 'opencart';
        $data['module_linnworks_price_field'] = $data['module_linnworks_price_field'] ?: 'purchase_price';

        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['success']);

        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['counts'] = $this->model_extension_linnworks_module_linnworks->counts();
        $data['logs'] = $this->model_extension_linnworks_module_linnworks->logs();
        $data['locations'] = [];

        try {
            if ($this->hasCredentials($data)) {
                $data['locations'] = $this->model_extension_linnworks_module_linnworks->locations(
                    $this->settingsFromData($data)
                );
            }
        } catch (\Throwable $exception) {
            $data['location_error'] = $exception->getMessage();
        }

        $data['endpoints'] = $this->channelEndpoints($data['module_linnworks_public_base_url']);

        $user_token = 'user_token=' . $this->session->data['user_token'];

        $data['breadcrumbs'] = [
            [
                'text' => 'Home',
                'href' => $this->url->link('common/dashboard', $user_token)
            ],
            [
                'text' => 'Extensions',
                'href' => $this->url->link('marketplace/extension', $user_token . '&type=module')
            ],
            [
                'text' => $data['heading_title'],
                'href' => ''
            ]
        ];

        $data['save'] = $this->url->link(
            'extension/linnworks/module/linnworks',
            $user_token,
            true
        );

        // OpenCart 4.1 uses the pipe separator for controller actions in URLs.
        $data['test'] = $this->url->link(
            'extension/linnworks/module/linnworks|test',
            $user_token,
            true
        );
        $data['locations'] = $data['locations'];
        $data['locations_url'] = $this->url->link(
            'extension/linnworks/module/linnworks|locations',
            $user_token,
            true
        );
        $data['scan'] = $this->url->link(
            'extension/linnworks/module/linnworks|scan',
            $user_token,
            true
        );
        $data['diagnostics'] = $this->url->link(
            'extension/linnworks/module/linnworks|diagnostics',
            $user_token,
            true
        );

        // The Twig template expects `locations` to be the action URL in one button.
        // Preserve the location list separately and pass the URL under the expected key.
        $data['stock_locations'] = $data['locations'];
        $data['locations'] = $data['locations_url'];

        $data['back'] = $this->url->link(
            'marketplace/extension',
            $user_token . '&type=module'
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput(
            $this->load->view('extension/linnworks/module/linnworks', $data)
        );
    }

    public function test(): void {
        $this->jsonResponse(function (): array {
            $session = $this->model_extension_linnworks_module_linnworks->authorize(
                $this->postedConnectionSettings()
            );

            $this->model_extension_linnworks_module_linnworks->log(
                'info',
                'connection',
                'Authentication passed',
                [
                    'Locality' => $session['Locality'] ?? '',
                    'Server' => $session['Server'] ?? '',
                    'sid_registration' => $session['sid_registration'] ?? ''
                ]
            );

            return [
                'success' => 'Connected successfully',
                'locality' => $session['Locality'] ?? '',
                'server' => $session['Server'] ?? '',
                'sid_registration' => $session['sid_registration'] ?? '',
                'ttl' => $session['TTL'] ?? ''
            ];
        });
    }

    public function locations(): void {
        $this->jsonResponse(function (): array {
            return [
                'success' => 'Locations retrieved',
                'locations' => $this->model_extension_linnworks_module_linnworks->locations(
                    $this->postedConnectionSettings()
                )
            ];
        });
    }

    public function scan(): void {
        $this->jsonResponse(function (): array {
            return [
                'success' => 'Catalogue scan complete',
                'scan' => $this->model_extension_linnworks_module_linnworks->scan()
            ];
        });
    }

    public function diagnostics(): void {
        $this->jsonResponse(function (): array {
            $checks = [
                [
                    'name' => 'PHP cURL',
                    'status' => function_exists('curl_init')
                ],
                [
                    'name' => 'OpenSSL',
                    'status' => extension_loaded('openssl')
                ]
            ];

            try {
                $session = $this->model_extension_linnworks_module_linnworks->authorize(
                    $this->postedConnectionSettings()
                );

                $checks[] = [
                    'name' => 'Linnworks authentication',
                    'status' => true,
                    'detail' => $session['Locality'] ?? ''
                ];
                $checks[] = [
                    'name' => 'sid_registration',
                    'status' => !empty($session['sid_registration'])
                ];
            } catch (\Throwable $exception) {
                $checks[] = [
                    'name' => 'Linnworks authentication',
                    'status' => false,
                    'detail' => $exception->getMessage()
                ];
            }

            return [
                'success' => 'Diagnostics complete',
                'checks' => $checks
            ];
        });
    }

    public function install(): void {
        if ($this->user->hasPermission('modify', 'extension/linnworks/module/linnworks')) {
            $this->load->model('extension/linnworks/module/linnworks');
            $this->model_extension_linnworks_module_linnworks->install();
        }
    }

    public function uninstall(): void {
        // Preserve integration identity, mappings and audit data.
    }

    private function jsonResponse(callable $callback): void {
        $this->load->language('extension/linnworks/module/linnworks');
        $response = [];

        if (!$this->user->hasPermission('modify', 'extension/linnworks/module/linnworks')) {
            $response = [
                'error' => $this->language->get('error_permission')
            ];
        } else {
            try {
                $this->load->model('extension/linnworks/module/linnworks');
                $response = $callback();
            } catch (\Throwable $exception) {
                $response = [
                    'error' => $exception->getMessage()
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }

    private function postedConnectionSettings(): array {
        return [
            'application_id' => (string)($this->request->post['module_linnworks_application_id'] ?? $this->config->get('module_linnworks_application_id')),
            'application_secret' => (string)($this->request->post['module_linnworks_application_secret'] ?? $this->config->get('module_linnworks_application_secret')),
            'token' => (string)($this->request->post['module_linnworks_token'] ?? $this->config->get('module_linnworks_token')),
            'auth_url' => (string)($this->request->post['module_linnworks_auth_url'] ?? $this->config->get('module_linnworks_auth_url')),
            'timeout' => (int)($this->request->post['module_linnworks_timeout'] ?? 30)
        ];
    }

    private function settingsFromData(array $data): array {
        return [
            'application_id' => $data['module_linnworks_application_id'],
            'application_secret' => $data['module_linnworks_application_secret'],
            'token' => $data['module_linnworks_token'],
            'auth_url' => $data['module_linnworks_auth_url'],
            'timeout' => $data['module_linnworks_timeout']
        ];
    }

    private function hasCredentials(array $data): bool {
        return !empty($data['module_linnworks_application_id'])
            && !empty($data['module_linnworks_application_secret'])
            && !empty($data['module_linnworks_token']);
    }

    private function channelEndpoints(string $base_url): array {
        $base_url = rtrim($base_url, '/');
        $paths = [
            'AddNewUser' => 'add-new-user',
            'UserConfig' => 'user-config',
            'SaveConfig' => 'save-config',
            'ConfigTest' => 'config-test',
            'ConfigDeleted' => 'config-deleted',
            'Orders' => 'orders',
            'Despatch' => 'despatch',
            'Cancel' => 'cancel',
            'Refund' => 'refund',
            'Products' => 'products',
            'InventoryUpdate' => 'inventory-update',
            'PriceUpdate' => 'price-update',
            'ShippingTags' => 'shipping-tags',
            'PaymentTags' => 'payment-tags'
        ];

        $endpoints = [];
        foreach ($paths as $name => $path) {
            $endpoints[$name . 'Endpoint'] = $base_url . '/linnworks-channel/' . $path;
        }

        return $endpoints;
    }

    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/linnworks/module/linnworks')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
