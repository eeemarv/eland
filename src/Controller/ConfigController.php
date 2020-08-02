<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\ConfigCnst;
use App\HtmlProcess\HtmlPurifier;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\StaticContentService;

class ConfigController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $tab,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        AssetsService $assets_service,
        HeadingRender $heading_render,
        ConfigService $config_service,
        StaticContentService $static_content_service,
        DateFormatService $date_format_service,
        IntersystemsService $intersystems_service,
        SelectRender $select_render,
        PageParamsService $pp,
        HtmlPurifier $html_purifier,
        string $env_s3_url
    ):Response
    {
        $errors = [];
        $pane = ConfigCnst::TAB_PANES[$tab];

        $cond_ary = [
            'config_template_lets'	=> true,
        ];

        $block_ary = ConfigCnst::BLOCK_ARY;

        if (!$config_service->get('forum_en', $pp->schema()))
        {
            unset($block_ary['forum']);
        }

        if (!$config_service->get_intersystem_en($pp->schema()))
        {
            unset($block_ary['intersystem']);
            unset($cond_ary['config_template_lets']);
        }

        $select_options = [
            'date_format'	=> $date_format_service->get_options(),
            'landing_page'	=> ConfigCnst::LANDING_PAGE_OPTIONS,
        ];

        $explain_replace_ary = [
            '%path_register%'	=> $link_render->path('register', ['system' => $pp->system()]),
            '%path_contact%'	=> $link_render->path('contact_form', ['system' => $pp->system()]),
        ];

        $addon_replace_ary = [
            '%config_currency%'	=> $config_service->get('currency', $pp->schema()),
        ];

        $attr_replace_ary = [
            '%map_template_vars%'	=> implode(',', array_keys(ConfigCnst::MAP_TEMPLATE_VARS)),
        ];

        $config = [];

        foreach ($pane['inputs'] as $input_name => $input_config)
        {
            if (is_array($input_config) && isset($input_config['inline']))
            {
                $inline_input_names = $this->get_tag_ary('input', $input_config['inline']);

                foreach ($inline_input_names as $inline_input_name)
                {
                    $config[$inline_input_name] = $config_service->get($inline_input_name, $pp->schema());
                }

                continue;
            }

            $input_field_cnf = ConfigCnst::INPUTS[$input_name];

            if (isset($input_field_cnf['static_content']))
            {
                $st_id = $input_field_cnf['static_content']['id'];
                $st_block = $input_field_cnf['static_content']['block'];
                $config[$input_name] = $static_content_service->get($st_id, $st_block, $pp->schema());
            }
            else
            {
                $path = $input_field_cnf['path'];

                if (isset($input_field_cnf['is_ary']))
                {
                    $ary_value = $config_service->get_ary($path, $pp->schema());
                    $config[$input_name] = implode(',', $ary_value);
                }
                else
                {
                    $config[$input_name] = $config_service->get($input_name, $pp->schema());
                }
            }
        }

        if ($request->isMethod('POST'))
        {
            if (!$request->request->get($tab . '_submit'))
            {
                $errors[] = 'Form submit error';
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            $posted_configs = [];

            foreach ($config as $input_name => $loaded_value)
            {
                if (!isset(ConfigCnst::INPUTS[$input_name]))
                {
                    continue;
                }

                $posted_value = trim($request->request->get($input_name, ''));
                $input_data = ConfigCnst::INPUTS[$input_name];

                if (isset($input_data['cond']) &&
                    !isset($cond_ary[$input_data['cond']]))
                {
                    continue;
                }

                $validator = [
                    'type'			=> $input_data['type'] ?? 'text',
                    'attr'			=> $input_data['attr'] ?? [],
                    'required'		=> isset($input_data['required']) ? true : false,
                    'max_inputs' 	=> $input_data['max_inputs'] ?? 1,
                ];

                $err_n = ' (' . $input_name . ')';

                if ($validator['required']
                    && $posted_value === '')
                {
                    $errors[] = 'Het veld is verplicht in te vullen.' . $err_n;
                    continue;
                }

                if ($validator['type'] === 'text'
                    || $validator['type'] === 'textarea')
                {
                    $posted_value = $html_purifier->purify($posted_value);
                }

                $posted_value = strip_tags($posted_value) !== '' ? $posted_value : '';

                if ($validator['type'] === 'checkbox')
                {
                    $posted_value = $posted_value ? '1' : '0';
                }

                if ($posted_value === $config[$input_name])
                {
                    continue;
                }

                $posted_configs[$input_name] = $posted_value;

                if ($input_name === 'date_format')
                {
                    $error = $date_format_service->get_error($posted_value);

                    if ($error)
                    {
                        $errors[] = $error . $err_n;
                    }

                    continue;
                }

                if ($validator['type'] === 'text'
                    || $validator['type'] === 'textarea')
                {
                    if (isset($validator['attr']['maxlength'])
                        && strlen($posted_value) > $validator['attr']['maxlength'])
                    {
                        $errors[] = 'Fout: de waarde mag maximaal ' .
                            $validator['attr']['maxlength'] .
                            ' tekens lang zijn.' . $err_n;
                    }

                    if (isset($validator['attr']['minlength'])
                        && strlen($posted_value) < $validator['attr']['minlength'])
                    {
                        $errors[] = 'Fout: de waarde moet minimaal ' .
                            $validator['attr']['minlength'] .
                            ' tekens lang zijn.' . $err_n;
                    }

                    continue;
                }

                if ($validator['type'] === 'number')
                {
                    if ($posted_value === '' && !$validator['required'])
                    {
                        continue;
                    }

                    if ($posted_value !== '0'
                        && !filter_var($posted_value, FILTER_VALIDATE_INT))
                    {
                        $errors[] = 'Fout: de waarde moet een getal zijn.' . $err_n;
                    }

                    if (isset($validator['attr']['max'])
                        && $posted_value > $validator['attr']['max'])
                    {
                        $errors[] = 'Fout: de waarde mag maximaal ' .
                            $validator['attr']['max'] .
                            ' bedragen.' . $err_n;
                    }

                    if (isset($validator['attr']['min'])
                        && $posted_value < $validator['attr']['min'])
                    {
                        $errors[] = 'Fout: de waarde moet minimaal ' .
                            $validator['attr']['min'] .
                            ' bedragen.' . $err_n;
                    }

                    continue;
                }

                if ($validator['type'] === 'checkbox')
                {
                    continue;
                }

                if ($validator['type'] === 'email')
                {
                    if (isset($validator['max_inputs']))
                    {
                        $mail_ary = explode(',', $posted_value);

                        if (count($mail_ary) > $validator['max_inputs'])
                        {
                            $errors[] = 'Maximaal ' .
                                $validator['max_inputs'] .
                                ' E-mail adressen mogen ingegeven worden.' .
                                $err_n;
                        }

                        foreach ($mail_ary as $m)
                        {
                            $m = trim($m);

                            if (!filter_var($m, FILTER_VALIDATE_EMAIL))
                            {
                                $errors[] =  $m . ' is geen geldig E-mail adres.' . $err_n;
                            }
                        }

                        continue;
                    }

                    if (!filter_var($posted_value, FILTER_VALIDATE_EMAIL))
                    {
                        $errors[] =  $posted_value . ' is geen geldig E-mail adres.' . $err_n;
                    }

                    continue;
                }

                if ($validator['type'] === 'url')
                {
                    if ($posted_value != '')
                    {
                        if (!filter_var($posted_value, FILTER_VALIDATE_URL))
                        {
                            $errors[] =  $posted_value . ' is geen geldig url adres.' . $err_n;
                        }
                    }

                    continue;
                }
            }

            if (!count($posted_configs))
            {
                $alert_service->warning('Geen gewijzigde waarden.');

                $link_render->redirect('config', $pp->ary(),
                    ['tab' => $tab]);
            }

            if (count($errors))
            {
                $alert_service->error($errors);

                $link_render->redirect('config', $pp->ary(),
                    ['tab' => $tab]);
            }

            $execute_post_actions = [];

            foreach ($posted_configs as $input_name => $posted_value)
            {
                $input_cnf = ConfigCnst::INPUTS[$input_name];
                $path = $input_cnf['path'] ?? '';
                $static_content_cnf = $input_cnf['static_content'] ?? [];

                if (isset($input_cnf['is_ary']))
                {
                    $posted_ary  = $posted_value === '' ? [] : explode(',', $posted_value);

                    if ($input_name = 'periodic_mail_block_ary')
                    {
                        $p_ary = $posted_ary;
                        $posted_ary = [];

                        foreach ($p_ary as $p)
                        {
                            [$block, $select] = explode('.', $p);

                            if (isset($block_ary[$block]) && isset($block_ary[$block]['all']))
                            {
                                $select = $select === 'all' ? 'all' : 'recent';
                                $config_service->set_str('periodic_mail.user.render.' . $block . '.select', $select, $pp->schema());
                            }

                            if (isset($block_ary[$block]))
                            {
                                $posted_ary[] = $block;
                            }
                        }
                    }

                    $config_service->set_ary($path, $posted_ary, $pp->schema());
                }
                else if (count($static_content_cnf))
                {
                    $st_id = $static_content_cnf['id'];
                    $st_block = $static_content_cnf['block'];
                    $static_content_service->set($st_id, $st_block, (string) $posted_value, $pp->schema());
                }
                else if (isset($input_cnf['type']))
                {
                    if ($input_cnf['type'] === 'checkbox')
                    {
                        $config_service->set_bool($path, $posted_value ? true : false, $pp->schema());
                    }
                    else if ($input_cnf['type'] === 'number')
                    {
                        if ($posted_value === '' || !isset($posted_value))
                        {
                            $config_service->set_int($path, null, $pp->schema());
                        }
                        else
                        {
                            $config_service->set_int($path, (int) $posted_value, $pp->schema());
                        }
                    }
                    else
                    {
                        $config_service->set_str($path, (string) $posted_value, $pp->schema());
                    }
                }
                else
                {
                    $config_service->set_str($path, (string) $posted_value, $pp->schema());
                }

 //               $config_service->set($input_name, $pp->schema(), $posted_value);

                $post_actions = ConfigCnst::INPUTS[$input_name]['post_actions'] ?? [];

                foreach($post_actions as $post_action)
                {
                    $execute_post_actions[$post_action] = true;
                }
            }

            if (isset($execute_post_actions['clear_eland_intersystem_cache']))
            {
                $intersystems_service->clear_eland_cache();
            }

            if (count($posted_configs) > 1)
            {
                $alert_service->success('De instellingen zijn aangepast.');
            }
            else
            {
                $alert_service->success('De instelling is aangepast.');
            }

            $link_render->redirect('config', $pp->ary(),
                ['tab' => $tab]);
        }

        if (isset(ConfigCnst::TAB_PANES[$tab]['assets']))
        {
            $assets_service->add(ConfigCnst::TAB_PANES[$tab]['assets']);
        }

        $heading_render->add('Instellingen');
        $heading_render->fa('gears');

        $out = '<div>';

        $out .= '<ul class="nav nav-pills">';

        foreach (ConfigCnst::TAB_PANES as $tab_id => $tab_pane_data)
        {
            $out .= '<li role="presentation" ';
            $out .= 'class="nav-item">';

            $class = 'nav-link';
            $class .= $tab_id === $tab ? ' active' : '';

            $out .= $link_render->link('config',
                $pp->ary(),
                ['tab' => $tab_id],
                $tab_pane_data['lbl'],
                ['class' => $class]
            );
            $out .= '</li>';
        }

        $out .= '</ul>';

        $out .= '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        $out .= '<h4>';
        $out .= $pane['lbl_pane'] ?? $pane['lbl'];
        $out .= '</h4>';

        if (isset($pane['explain']))
        {
            $out .= '<p>';
            $out .= $pane['explain'];
            $out .= '</p>';
        }

        $out .= '</div>';

        if ($tab === 'logo')
        {
            $out .= self::render_logo(
                $config_service,
                $link_render,
                $assets_service,
                $pp,
                $env_s3_url
            );
        }

        $out .= '<ul class="list-group">';

        foreach ($pane['inputs'] as $pane_input_name => $pane_input_value)
        {
            if (is_array($pane_input_value))
            {
                $input = $pane_input_value;
            }
            else
            {
                if (!isset(ConfigCnst::INPUTS[$pane_input_name]))
                {
                    continue;
                }

                $input = ConfigCnst::INPUTS[$pane_input_name];
                $input_name = $pane_input_name;
            }

            if (isset($input['cond']) &&
            !isset($cond_ary[$input['cond']]))
            {
                continue;
            }

            $out .= '<li class="list-group-item fcard fcard-info">';

            if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
            {
                $out .= '<input type="hidden" value="';
                $out .= $config[$input_name];
                $out .= '" ';
                $out .= 'data-max-inputs="';
                $out .= $input['max_inputs'];
                $out .= '" ';
                $out .= 'name="';
                $out .= $input_name;
                $out .= '">';

                $name_suffix = '_0';
            }
            else
            {
                $name_suffix = '';
            }

            if (isset($input['inline']))
            {
                $replace_inline_ary = [];
                $checkbox_id = '';

                $inline_input_names = $this->get_tag_ary('input', $input['inline']);

                foreach ($inline_input_names as $inline_input_name)
                {
                    $inline_input_data = ConfigCnst::INPUTS[$inline_input_name];

                    $str = '';

                    if ($inline_input_data['type'] == 'checkbox')
                    {
                        $str .= '<div class="custom-control custom-checkbox">';
                    }

                    $str .= '<input type="';
                    $str .= $inline_input_data['type'] ?? 'text';
                    $str .= '" name="';
                    $str .= $inline_input_name;
                    $str .= '"';

                    if ($inline_input_data['type'] == 'checkbox')
                    {
                        $checkbox_id = 'inline_id_' . $inline_input_name;
                        $str .= ' id="' . $checkbox_id . '"';
                        $str .= ' value="1"';
                        $str .= ' class="custom-control-input" ';
                        $str .= $config[$inline_input_name] ? ' checked ' : '';
                    }
                    else
                    {
                        $str .= ' class="sm-size"';
                        $str .= ' value="';
                        $str .= $config[$inline_input_name];
                        $str .= '"';
                    }

                    if (isset($inline_input_data['attr']))
                    {
                        foreach ($inline_input_data['attr'] as $attr_name => $attr_value)
                        {
                            $str .= ' ';
                            $str .= $attr_name;
                            $str .= '="';
                            $str .=  strtr($attr_value, $attr_replace_ary);
                            $str .= '"';
                        }
                    }

                    $str .= isset($inline_input_data['required']) ? ' required' : '';

                    $str .= '>';

                    if ($inline_input_data['type'] == 'checkbox')
                    {
                        $str .= '<label ';
                        $str .= 'for="' . $checkbox_id . '" ';
                        $str .= 'class="custom-control-label">';
                    }

                    $search_inline = ConfigCnst::TAG['input']['open'];
                    $search_inline .= $inline_input_name;
                    $search_inline .= ConfigCnst::TAG['input']['close'];

                    $replace_inline_ary[$search_inline] = $str;
                }

                $out .= '<p>';

                $out .= strtr($input['inline'], $replace_inline_ary);

                if ($checkbox_id)
                {
                    $out .= '</label>';
                    $out .= '</div>';
                }

                $out .= '</p>';
            }
            else if (isset($input['type'])
                && $input['type'] === 'sortable'
                && $input_name === 'periodic_mail_block_ary'
                && isset($input['is_ary']))
            {
                $v_options = $v_input = $active = $inactive = [];

                foreach ($ary_value as $block)
                {
                    if (!$block)
                    {
                        continue;
                    }

                    $v_options[$block] = 'recent';

                    if (isset($block_ary[$block]) && isset($block_ary[$block]['all']))
                    {
                        $select = $config_service->get_str('periodic_mail.user.render.' . $block . '.select', $pp->schema());
                        if ($select === 'all')
                        {
                            $v_options[$block] = 'all';
                        }
                    }

                    $active[] = $block;
                    $v_input[] = $block . '.' . $v_options[$block];
                }

                foreach ($block_ary as $block => $options)
                {
                    if (isset($v_options[$block]))
                    {
                        continue;
                    }

                    $inactive[] = $block;
                }

                $out .= isset($input['lbl']) ? '<h4>' . $input['lbl'] . '</h4>' : '';

                if (isset($input['explain_top']))
                {
                    $out .= '<p>';
                    $out .= $input['explain_top'];
                    $out .= '</p>';
                }

                $out .= '<div class="row">';

                $out .= '<div class="col-md-6">';
                $out .= '<div class="card fcard fcard-default">';
                $out .= '<div class="card-body">';

                if (isset($input['lbl_active']))
                {
                    $out .= '<h5>';
                    $out .= $input['lbl_active'];
                    $out .= '</h5>';
                }

                $out .= '</div>';
                $out .= '<div class="card-body">';
                $out .= '<ul id="list_active" class="list-group">';

                $out .= $this->get_sortable_items_str(
                    $block_ary,
                    $v_options,
                    $active,
                    'bg-success');

                $out .= '</ul>';
                $out .= '</div>';
                $out .= '</div>';
                $out .= '</div>'; // col

                $out .= '<div class="col-md-6">';
                $out .= '<div class="card fcard fcard-default">';
                $out .= '<div class="card-body">';

                if (isset($input['lbl_inactive']))
                {
                    $out .= '<h5>';
                    $out .= $input['lbl_inactive'];
                    $out .= '</h5>';
                }

                $out .= '</div>';
                $out .= '<div class="card-body">';
                $out .= '<ul id="list_inactive" class="list-group">';

                $out .= $this->get_sortable_items_str(
                    $block_ary,
                    $v_options,
                    $inactive,
                    'bg-danger');

                $out .= '</ul';
                $out .= '</div>';
                $out .= '</div>';
                $out .= '</div>'; // col

                $out .= '</div>'; // row

                $out .= '<input type="hidden" name="';
                $out .= $input_name;
                $out .= '" ';
                $out .= 'value="';
                $out .= implode(',', $v_input);
                $out .= '" id="';
                $out .= $input_name;
                $out .= '">';
            }
            else
            {
                $out .= '<div class="form-group">';

                if (isset($input['lbl']))
                {
                    $out .= '<label class="control-label">';
                    $out .= $input['lbl'];
                    $out .= '</label>';
                }

                if (isset($input['explain_top']))
                {
                    $out .= '<p>';
                    $out .= $input['explain_top'];
                    $out .= '</p>';
                }

                if (isset($input['addon']) || isset($input['addon_fa']))
                {
                    $out .= '<div class="input-group">';
                    $out .= '<span class="input-group-prepend">';
                    $out .= '<span class="input-group-text">';

                    if (isset($input['addon']))
                    {
                        $out .= strtr($input['addon'], $addon_replace_ary);
                    }

                    if (isset($input['addon_fa']))
                    {
                        $out .= '<i class="fa fa-';
                        $out .= $input['addon_fa'];
                        $out .= '"></i>';
                    }

                    $out .= '</span>';
                    $out .= '</span>';
                }

                if (isset($input['type']) && $input['type'] === 'select')
                {
                    $out .= '<select class="form-control" name="';
                    $out .= $input_name . '"';
                    $out .= isset($input['required']) ? ' required' : '';
                    $out .= '>';

                    $out .= $select_render->get_options($select_options[$input['options']],
                        $config[$input_name]);

                    $out .= '</select>';
                }
                else if (isset($input['type']) && $input['type'] === 'textarea')
                {
                    $out .= '<textarea name="' . $input_name . '" ';
                    $out .= 'id="' . $input_name . '" class="form-control';
                    $out .= isset($input['summernote']) ? ' summernote' : '';
                    $out .= '" rows="4"';

                    $out .= isset($input['attr']['maxlength']) ? '' : ' maxlength="2000"';
                    $out .= isset($input['attr']['minlength']) ? '' : ' minlength="1"';
                    $out .= isset($input['required']) ? ' required' : '';

                    if (isset($input['attr']))
                    {
                        foreach ($input['attr'] as $attr_name => $attr_value)
                        {
                            $out .= ' ' . $attr_name . '="';
                            $out .= strtr($attr_value, $attr_replace_ary);
                            $out .= '"';
                        }
                    }

                    $out .= '>';
                    $out .= $config[$input_name];
                    $out .= '</textarea>';
                }
                else
                {
                    $out .= '<input type="';
                    $out .= $input['type'] ?? 'text';
                    $out .= '" class="form-control" ';
                    $out .= 'name="' . $input_name . $name_suffix . '" ';
                    $out .= 'id="' . $input_name . $name_suffix . '" ';
                    $out .= 'value="';
                    $out .= $config[$input_name];
                    $out .= '"';

                    $out .= isset($input['attr']['maxlength']) ? '' : ' maxlength="60"';
                    $out .= isset($input['attr']['minlength']) ? '' : ' minlength="1"';
                    $out .= isset($input['required']) ? ' required' : '';

                    if (isset($input['attr']))
                    {
                        foreach ($input['attr'] as $attr_name => $attr_value)
                        {
                            $out .= ' ' . $attr_name . '="';
                            $out .= strtr($attr_value, $attr_replace_ary);
                            $out .= '"';
                        }
                    }

                    $out .= '>';
                }

                if (isset($input['addon']) || isset($input['addon_fa']))
                {
                    $out .= '</div>';
                }

                $out .= '</div>';
            }

            if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
            {
                $out .= '<div class="form-group hidden add-input">';
                $out .= '<div class="extra-field">';
                $out .= '<br>';
                $out .= '<span class="btn btn-default"><i class="fa fa-plus" ></i> ';
                $out .= $input['add_btn_text'] ?? 'Extra';
                $out .= '</span>';
                $out .= '</div>';
                $out .= '</div>';
            }

            if (isset($input['explain']))
            {
                $out .= '<p>';
                $out .= strtr($input['explain'], $explain_replace_ary);
                $out .= '</p>';
            }

            $out .= '</li>';
        }

        $out .= '</ul>';

        $out .= '<div class="card-body">';

        $out .= '<input type="hidden" name="tab" value="' . $tab . '">';

        if (count($pane['inputs']))
        {
            $out .= '<input type="submit" class="btn btn-primary btn-lg" ';
            $out .= 'value="Aanpassen" name="' . $tab . '_submit">';
        }

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $menu_service->set('config');

        return $this->render('config/config.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    private function get_tag_ary(string $tag_name, string $line):array
    {
        $return_ary = [];

        $open = ConfigCnst::TAG[$tag_name]['open'];
        $close = ConfigCnst::TAG[$tag_name]['close'];

        $start = 0;

        do
        {
            $start = strpos($line, $open, $start);

            if ($start === false)
            {
                return $return_ary;
            }

            $start += strlen($open);

            $end = strpos($line, $close, $start);

            if ($end === false)
            {
                return $return_ary;
            }

            $return_ary[] = substr($line, $start, $end - $start);

            $start = $end + strlen($close);
        }
        while ($start < strlen($line));

        return $return_ary;
    }

    private function get_sortable_items_str(
        array $input_ary,
        array $v_options,
        array $items,
        string $class
    ):string
    {
        $out = '';

        foreach ($items as $a)
        {
            if (!isset($input_ary[$a]))
            {
                continue;
            }

            $options = $input_ary[$a];

            if (!count($options))
            {
                continue;
            }
            else if (count($options) === 1)
            {
                $lbl = reset($options);
                $option = key($options);
                $out .= '<li class="list-group-item ';
                $out .= $class;
                $out .= '" ';
                $out .= 'data-block="';
                $out .= $a;
                $out .= '" ';
                $out .= 'data-option="';
                $out .= $option;
                $out .= '" >';
                $out .= '<span class="lbl">';
                $out .= $lbl;
                $out .= '</span>';
                $out .= '</li>';

                continue;
            }

            if (isset($v_options[$a]))
            {
                $option = $v_options[$a];
                $lbl = $options[$option];
            }
            else
            {
                $lbl = reset($options);
                $option = key($options);
            }

            $out .= '<li class="list-group-item ';
            $out .= $class;
            $out .= '" ';
            $out .= 'data-block="';
            $out .= $a;
            $out .= '" ';
            $out .= 'data-option="';
            $out .= $option;
            $out .= '">';
            $out .= '<span class="lbl">';
            $out .= $lbl;
            $out .= '</span>';
            $out .= '&nbsp;&nbsp;';
            $out .= '<button type="button" class="btn btn-default ';
            $out .= 'dropdown-toggle" ';
            $out .= 'data-toggle="dropdown" aria-haspopup="true" ';
            $out .= 'aria-expanded="false">';
            $out .= ' <span class="caret"></span>';
            $out .= '</button>';
            $out .= '<ul class="dropdown-menu">';

            foreach ($options as $k => $lbl)
            {
                $out .= '<li><a href="#" data-o="';
                $out .= $k;
                $out .= '">';
                $out .= $lbl;
                $out .= '</a></li>';
            }

            $out .= '</ul></li>';
        }

        for ($i = 0; $i < 5; $i++)
        {
            $out .= '<li class="list-group-item"></li>';
        }

        return $out;
    }

    static function render_logo(
        ConfigService $config_service,
        LinkRender $link_render,
        AssetsService $assets_service,
        PageParamsService $pp,
        string $env_s3_url
    )
    {
        $logo = $config_service->get_str('system.logo', $pp->schema());

        $out = '<div class="card-body">';
        $out .= '<div class="col-md-6">';

        $out .= '<div class="text-center ';
        $out .= 'center-block" id="img_user">';

        $show_logo = $logo ? true : false;

        $out .= '<img id="img"';
        $out .= $show_logo ? '' : ' style="display:none;"';
        $out .= ' class="img-rounded img-responsive center-block" ';
        $out .= 'src="';

        if ($show_logo)
        {
            $out .= $env_s3_url . $logo;
        }
        else
        {
            $out .= $assets_service->get('1.gif');
        }

        $out .= '" ';
        $out .= 'data-base-url="' . $env_s3_url . '" ';
        $out .= 'data-replace-logo="1">';

        $out .= '<div id="no_img"';
        $out .= $show_logo ? ' style="display:none;"' : '';
        $out .= '>';
        $out .= '<i class="fa fa-image fa-5x text-muted"></i>';
        $out .= '<br>Geen logo';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<br>';

        $btn_del_attr = ['id'	=> 'btn_remove'];

        if (!$show_logo)
        {
            $btn_del_attr['style'] = 'display:none;';
        }

        $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
        $out .= '<i class="fa fa-plus" id="img_plus"></i> Logo opladen';
        $out .= '<input type="file" name="image" ';
        $out .= 'data-url="';

        $out .= $link_render->context_path('logo_upload', $pp->ary(), []);

        $out .= '" ';
        $out .= 'data-fileupload ';
        $out .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
        $out .= 'data-message-max-file-size="Het bestand is te groot." ';
        $out .= 'data-message-uploaded-bytes="Het bestand is te groot.">';
        $out .= '</span>';

        $out .= '<p class="text-warning">';
        $out .= 'Toegestane formaten: jpg/jpeg, png, gif of svg. ';
        $out .= 'Je kan ook een afbeelding hierheen verslepen. ';
        $out .= '</p>';

        $out .= $link_render->link_fa('logo_del', $pp->ary(),
            [], 'Logo verwijderen',
            array_merge($btn_del_attr, ['class' => 'btn btn-danger btn-lg btn-block']),
            'times');

        $out .= '</div>';
        $out .= '</div>';

        return $out;
    }
}
