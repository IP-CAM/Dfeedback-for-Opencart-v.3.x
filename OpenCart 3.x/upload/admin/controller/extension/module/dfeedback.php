<?php
/**
 * Controller Module D.Feedback Class
 *
 * @version 1.0
 * 
 * @author D.art <d.art.reply@gmail.com>
 */

class ControllerExtensionModuleDFeedback extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/dfeedback');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/module');
        $this->load->model('setting/extension');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            if (!isset($this->request->get['module_id'])) {
                $this->model_setting_module->addModule('dfeedback', $this->request->post);
                $module_id = $this->db->getLastId();

                $module_settings = $this->model_setting_module->getModule($module_id);
                $module_settings['module_id'] = $module_id;

                $this->model_setting_module->editModule($module_id, $module_settings);
            } else {
                $post = $this->request->post;
                $post['module_id'] = $this->request->get['module_id'];
                $this->model_setting_module->editModule($this->request->get['module_id'], $post);
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['name'])) {
            $data['error_name'] = $this->error['name'];
        } else {
            $data['error_name'] = '';
        }

        if (isset($this->error['form'])) {
            $data['error_form'] = $this->error['form'];
        } else {
            $data['error_form'] = array();
        }

        $url = '';

        if (isset($this->request->get['module_id'])) {
            $url .= '&module_id=' . $this->request->get['module_id'];
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dfeedback', 'user_token=' . $this->session->data['user_token'] . $url, true)
        );

        $data['action'] = $this->url->link('extension/module/dfeedback', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        if (isset($this->request->get['module_id'])) {
            $module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
        }

        if (isset($this->request->post['name'])) {
            $data['name'] = $this->request->post['name'];
        } elseif (!empty($module_info)) {
            $data['name'] = $module_info['name'];
        } else {
            $data['name'] = '';
        }

        if (isset($this->request->post['uniqid'])) {
            $data['uniqid'] = $this->request->post['uniqid'];
        } elseif (!empty($module_info)) {
            $data['uniqid'] = $module_info['uniqid'];
        } else {
            $data['uniqid'] = uniqid(time(), false);
        }

        if (isset($this->request->post['mcode'])) {
            $data['mcode'] = $this->request->post['mcode'];
        } elseif (!empty($module_info)) {
            $data['mcode'] = $module_info['mcode'];
        } else {
            $data['mcode'] = $this->generateRandomString(8);
        }

        if (isset($this->request->post['attr_ID'])) {
            $data['attr_ID'] = $this->request->post['attr_ID'];
        } elseif (!empty($module_info)) {
            $data['attr_ID'] = $module_info['attr_ID'];
        } else {
            $data['attr_ID'] = '';
        }

        if (isset($this->request->post['captcha'])) {
            $data['captcha'] = $this->request->post['captcha'];
        } elseif (!empty($module_info)) {
            $data['captcha'] = $module_info['captcha'];
        } else {
            $data['captcha'] = '';
        }

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($module_info)) {
            $data['status'] = $module_info['status'];
        } else {
            $data['status'] = '';
        }

        if (isset($this->request->post['module_description'])) {
            $data['module_description'] = $this->request->post['module_description'];
        } elseif (!empty($module_info)) {
            $data['module_description'] = $module_info['module_description'];
        } else {
            $data['module_description'] = array();
        }

        $this->load->model('tool/image');

        /* Languages */

        $this->load->model('localisation/language');

        $data['languages'] = $this->model_localisation_language->getLanguages();

        /* Form */

        if (isset($this->request->post['form'])) {
            $form = $this->request->post['form'];
        } elseif (!empty($module_info) && isset($module_info['form'])) {
            $form = $module_info['form'];
        } else {
            $form = array();
        }

        $data['form'] = array();

        foreach ($form as $key => $value) {
            foreach ($value as $field) {
                $data['form'][$key][] = array(
                    'name'           => $field['name'],
                    'type'           => $field['type'],
                    'select_options' => $field['select_options'],
                    'required'       => $field['required'],
                    'sort_order'     => $field['sort_order'],
                    'field_name'     => $field['field_name']
                );
            }
        }

        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        /* Captchas */

        $data['captchas'] = array();

        // Get a list of installed captchas
        $extensions = $this->model_setting_extension->getInstalled('captcha');

        if (version_compare(VERSION, '3.0.0.0', '>')) {
            foreach ($extensions as $code) {
                $this->load->language('extension/captcha/' . $code, 'extension');

                if ($this->config->get('captcha_' . $code . '_status')) {
                    $data['captchas'][] = array(
                        'text'  => $this->language->get('extension')->get('heading_title'),
                        'value' => $code
                    );
                }
            }
        } else {
            $language = new Language($this->config->get('config_language'));

            foreach ($extensions as $code) {
                $language->load('extension/captcha/' . $code);

                if ($this->config->get('captcha_' . $code . '_status')) {
                    $data['captchas'][] = array(
                        'text'  => $language->get('heading_title'),
                        'value' => $code
                    );
                }
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dfeedback', $data));
    }

    /**
     * Validate Permission and Form fiels.
     * 
     * @return bool $this->error
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dfeedback')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        if (isset($this->request->post['form'])) {
            foreach ($this->request->post['form'] as $language_id => $value) {
                foreach ($value as $field) {
                    if ((utf8_strlen($field['name']) < 3) || (utf8_strlen($field['name']) > 64)) {
                        $this->error['form'][$language_id][$field['field_name']]['name'] = $this->language->get('error_row_name');
                    }

                    if (empty($field['type'])) {
                        $this->error['form'][$language_id][$field['field_name']]['type'] = $this->language->get('error_row_type');
                    }
                }
            }
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }

    /**
    * Install method.
    *
    * @return void
    */
    public function install() {
        $this->load->model('setting/setting');

        // Add settings.
        $this->model_setting_setting->editSetting('module_dfeedback', array(
            'module_dfeedback_captcha_ed_pc' => $this->generateRandomString(8), // Password for encrypt/decrypt module_id settings.
            'module_dfeedback_captcha_ed_ec' => $this->generateRandomString(8)  // Secure string for encrypt/decrypt module_id settings.
        ));
    }

    /**
    * Uninstall method.
    *
    * @return void
    */
    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_dfeedback');
    }

    /**
    * Generate random string.
    *
    * @param int $length
    *
    * @return string $string
    */
    private function generateRandomString($length = 16) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $characters_length = strlen($characters);
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, $characters_length - 1)];
        }

        return $string;
    }
}