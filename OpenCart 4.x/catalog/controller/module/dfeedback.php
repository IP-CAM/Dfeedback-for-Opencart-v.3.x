<?php
/**
 * Controller Module D.Feedback Class
 *
 * @version 1.0
 * 
 * @author D.art <d.art.reply@gmail.com>
 */

namespace Opencart\Catalog\Controller\Extension\DFeedback\Module;
class DFeedback extends \Opencart\System\Engine\Controller {
    private $error = array();
    private $datetimepicker = false;

    private $x = '|';

    public function index(array $setting): string {
        $view = '';
        $language_id = $this->config->get('config_language_id');

        if (isset($setting['form'][$language_id])) {
            $this->x = version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';

            $this->load->language('extension/dfeedback/module/dfeedback');

            $this->load->model('setting/extension');

            $this->document->addStyle(HTTP_SERVER . 'extension/dfeedback/catalog/view/javascript/module-dfeedback/dfeedback.css');

            static $module = 0;

            $data['form'] = $setting['form'][$language_id];

            usort($data['form'], function($a, $b){
                return strcmp($a['sort_order'], $b['sort_order']);
            });

            // Change Form Data.
            $data['form'] = $this->changeFormData($data['form']);

            // Add Datetimepicker Script.
            if ($data['datetimepicker'] = $this->datetimepicker) {
                $this->document->addStyle(HTTP_SERVER . 'extension/dfeedback/catalog/view/javascript/module-dfeedback/datetimepicker-1.3.6/jquery.datetimepicker.min.css');
                $this->document->addScript(HTTP_SERVER . 'extension/dfeedback/catalog/view/javascript/module-dfeedback/datetimepicker-1.3.6/jquery.datetimepicker.full.min.js');
            }

            $data['heading_title'] = html_entity_decode($setting['module_description'][$language_id]['title'], ENT_QUOTES, 'UTF-8');
            $data['description'] = html_entity_decode($setting['module_description'][$language_id]['description'], ENT_QUOTES, 'UTF-8');
            $data['attr_ID'] = $setting['attr_ID'];

            /* Captcha */

            if (empty($setting['captcha'])) {
                $data['captcha'] = '';
            } else {
                $extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $setting['captcha']);
                if ($extension_info && $this->config->get('captcha_' . $setting['captcha'] . '_status')) {
                    $data['captcha'] = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code']);
                } else {
                    $data['captcha'] = '';
                }
            }

            $fcode  = '';
            $fcode .= $setting['uniqid'] . '---';
            $fcode .= $setting['module_id'] . '---';
            $fcode .= $setting['mcode'] . '---';
            $fcode .= $this->config->get('module_dfeedback_captcha_ed_ec');

            $data['fcode'] = openssl_encrypt($fcode, 'AES-128-ECB', $this->config->get('module_dfeedback_captcha_ed_pc'));

            $data['action'] = $this->url->link('extension/dfeedback/module/dfeedback' . $this->x . 'submit', '');

            $data['module'] = $module++;

            $view = $this->load->view('extension/dfeedback/module/dfeedback', $data);
        }

        return $view;
    }

    /**
     * Submit Form.
     * AJAX.
     * 
     * @return void
     */
    public function submit(): void {
        $this->x = version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';

        $this->load->language('extension/dfeedback/module/dfeedback');

        $this->load->model('setting/module');

        $json = array();
        $json['success'] = false;
        $json['error']['general'] = $this->language->get('error_uniqid');

        if (isset($this->request->post['fcode'])) {
            $fcode = openssl_decrypt($this->request->post['fcode'], 'AES-128-ECB', $this->config->get('module_dfeedback_captcha_ed_pc'));
        } else {
            $fcode = false;
        }

        if (isset($this->request->post['fields'])) {
            $fields = $this->request->post['fields'];
        } else {
            $fields = array();
        }

        if ($fcode) {
            if (!empty($fields)) {
                $uniq_keys = explode('---', (string)$fcode);

                if (count($uniq_keys) == 4) {
                    $setting = $this->model_setting_module->getModule((int)$uniq_keys[1]);

                    if (!empty($setting) && isset($setting['uniqid']) && 
                        ($setting['uniqid'] == $uniq_keys[0]) && 
                        isset($setting['mcode']) && ($setting['mcode'] == $uniq_keys[2]) && 
                        ($uniq_keys[3] == $this->config->get('module_dfeedback_captcha_ed_ec'))) {
                            if ($this->validate($fields, $setting)) {
                                $this->sendMail($fields, $setting);

                                $json['success'] = true;
                                $json['text_success'] = $this->language->get('text_success');
                            } else {
                                $json['error'] = $this->error;

                                if (isset($json['error']['form']['fields'])) {
                                    $json['error']['fields'] = $this->language->get('error_fields');
                                }
                            }

                            $json['error']['general'] = '';
                    }
                }
            } else {
                $json['error']['general'] = '';
                $json['error']['fields'] = $this->language->get('error_fields');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Change Form Data.
     * 
     * @param array $form
     * 
     * @return array $form
     */
    private function changeFormData(array $form): array {
        $form_length = count($form);
        $select_options_key = 0;

        for ($i = 0; $i < $form_length; $i++) {
            $select_options_key = 0;
            $select_options_arr = array();

            // Check if field has type 'datetime'.
            switch ($form[$i]['type']) {
                case 'datetime':
                case 'date':
                case 'time':
                    $this->datetimepicker = true;
                    break;
                default:
                    break;
            }

            // Convert Options data to array.
            if (!empty($form[$i]['select_options'])) {
                $lines_str = trim($form[$i]['select_options']);

                $lines_str = preg_replace('/ *?\r */', '', $lines_str);
                $lines_str = preg_replace('/ *?\n */', '\n', $lines_str);
                $lines_str = preg_replace('/ *?: */', ':', $lines_str);

                $lines_arr = explode('\n', $lines_str);

                foreach ($lines_arr as &$line) {
                    $line = explode(':', $line);

                    if (count($line) > 1) {
                        $select_options_arr[$line[0]] = trim($line[1]);
                    } else {
                        $select_options_arr[$select_options_key] = trim($line[0]);
                        $select_options_key++;
                    }
                }

                //ksort($select_options_arr, SORT_STRING);

                $form[$i]['select_options'] = $select_options_arr;
                $select_options_arr = array();
            }
        }

        return $form;
    }

    /**
     * Send Mail.
     * 
     * @param array $fields
     * @param array $setting
     * 
     * @return void
     */
    private function sendMail(array $fields, array $setting): void {
		if ($this->config->get('config_mail_engine')) {
            if (version_compare(VERSION, '4.0.2.0', '>=')) {
                $mail_option = [
                    'parameter'     => $this->config->get('config_mail_parameter'),
                    'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
                    'smtp_username' => $this->config->get('config_mail_smtp_username'),
                    'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
                    'smtp_port'     => $this->config->get('config_mail_smtp_port'),
                    'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
                ];

                $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
            } else {
                $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'));
                $mail->parameter = $this->config->get('config_mail_parameter');
                $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
            }

            $mail->setTo($this->config->get('config_email'));
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(html_entity_decode(sprintf($this->language->get('email_subject'), $setting['name']), ENT_QUOTES, 'UTF-8'));
            $mail->setHtml($this->htmlMail($fields, $setting));
            $mail->send();
		}
    }

    /**
     * HTML Mail Template.
     * 
     * @param array $fields
     * @param array $setting
     * 
     * @return string $html
     */
    private function htmlMail(array $fields, array $setting): string {
        $language_id = $this->config->get('config_language_id');

        $html = '<div>';

        foreach($setting['form'][$language_id] as $field_setting) {
            foreach($fields as $key_post => $field_post) {
                if ($field_setting['field_name'] == $key_post) {
                    switch ($field_setting['type']) {
                        case 'checkbox':
                            $html .= '<div>' . $field_setting['name'] . ': ';

                            $index = 1;
                            $field_post_length = count($field_post);
                            foreach($field_post as $value) {
                                $html .= $value;

                                if ($index < $field_post_length) {
                                    $html .= ', ';
                                }

                                $index++;
                            }

                            $html .= '</div>';
                            break;

                        // etc.
                        default:
                            $html .= '<div>' . $field_setting['name'] . ': ' . trim($field_post). '</div>';
                            break;
                    }

                    break;
                }
            }
        }

        $html .= '<div>-------------</div>';
        $html .= '<div>' . $this->language->get('email_lang') . ': ' . $this->language->get('code'). '</div>';
        $html .= '<div>-------------</div>';
        $html .= '<div>' . html_entity_decode(sprintf($this->language->get('email_subject'), $setting['name']), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Validate Form Data.
     * 
     * @param array $fields
     * @param array $setting
     * 
     * @return bool
     */
    protected function validate(array $fields, array $setting): bool {
        $this->load->model('setting/extension');

        $language_id = $this->config->get('config_language_id');

        foreach($setting['form'][$language_id] as $field_setting) {
            if (($field_setting['type'] == 'checkbox' || $field_setting['type'] == 'radio') && 
                $field_setting['required'] && !isset($fields[$field_setting['field_name']])) {
                    $this->error['form']['fields'][$field_setting['field_name']] = $this->language->get('error_field');
            } else {
                foreach($fields as $key_post => $field_post) {
                    if ($field_setting['field_name'] == $key_post) {
                        if ($field_setting['required']) {
                            if (($field_setting['type'] == 'text') && ((mb_strlen($field_post) < 3) || (mb_strlen($field_post) > 64))) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_text');
                            }

                            if (($field_setting['type'] == 'textarea') && (mb_strlen($field_post) < 3)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_tarea');
                            }

                            if (($field_setting['type'] == 'email') && !filter_var($field_post, FILTER_VALIDATE_EMAIL)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }

                            if (($field_setting['type'] == 'tel') && !preg_match('/^[0-9]{7,}$/', preg_replace('/[^0-9]+/', '', $field_post))) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_tel');
                            }

                            if (($field_setting['type'] == 'select') && (mb_strlen($field_post) < 1)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }

                            if (($field_setting['type'] == 'checkbox') && empty($field_post)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }

                            if (($field_setting['type'] == 'radio') && (mb_strlen($field_post) < 1)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }

                            if (($field_setting['type'] == 'datetime') && (mb_strlen($field_post) < 1)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }

                            if (($field_setting['type'] == 'date') && (mb_strlen($field_post) < 1)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }

                            if (($field_setting['type'] == 'time') && (mb_strlen($field_post) < 1)) {
                                $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                            }
                        } else {
                            if (!is_array($field_post) && mb_strlen($field_post) > 0) {
                                if (($field_setting['type'] == 'email') && !filter_var($field_post, FILTER_VALIDATE_EMAIL)) {
                                    $this->error['form']['fields'][$key_post] = $this->language->get('error_field');
                                }

                                if (($field_setting['type'] == 'tel') && !preg_match('/^[0-9]{7,}$/', preg_replace('/[^0-9]+/', '', $field_post))) {
                                    $this->error['form']['fields'][$key_post] = $this->language->get('error_tel');
                                }
                            }
                        }

                        break;
                    }
                }
            }
        }

        /* Captcha */
        if ($setting['captcha']) {
            $extension_info = $this->model_setting_extension->getExtensionByCode('captcha', $setting['captcha']);

            if ($extension_info && $this->config->get('captcha_' . $setting['captcha'] . '_status')) {
                $captcha = $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . $this->x . 'validate');

                if ($captcha) {
                    $this->error['form']['captcha'] = $captcha;
                }
            } else {
                $this->error['form']['captcha'] = 'Error: Captcha is not valid!';
            }
        }

        return !$this->error;
    }
}