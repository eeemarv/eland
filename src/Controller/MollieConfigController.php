<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;

class MollieConfigController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        MenuService $menu_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        FormTokenService $form_token_service
    ):Response
    {
        $errors = [];

        $apikey = $request->request->get('apikey', '');

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if ($apikey !== ''
                && strpos($apikey, 'live_') !== 0
                && strpos($apikey, 'test_') !== 0)
            {
                $errors[] = 'De Mollie apikey moet beginnen met <code>live_</code> of <code>test_</code>';
            }

            if (!count($errors))
            {
                $data = $db->fetchColumn('select data
                    from ' . $pp->schema() . '.config
                    where id = \'mollie\'');

                $data = json_decode($data, true);
                $data['apikey'] = $apikey;
                $data = json_encode($data);

                $db->update($pp->schema() . '.config',
                    ['data' => $data, 'user_id' => $su->id()],
                    ['id' => 'mollie']);

                $alert_service->success('De Mollie Apikey is aangepast.');
                $link_render->redirect('mollie_payments', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }
        else
        {
            $apikey = $db->fetchColumn('select data->>\'apikey\'
                from ' . $pp->schema() . '.config
                where id = \'mollie\'');

            if ($apikey === false)
            {
                $apikey = '';
            }
        }

        $heading_render->add('Mollie configuratie');
        $heading_render->fa('eur');

        $out = '<div class="card bg-info">';
        $out .= '<div class="panel-heading"><p>';
        $out .= 'Om betalingen met <a href="https://www.mollie.com/nl/">Mollie</a> te ontvangen moet ';
        $out .= 'moet je op de <a href="https://www.mollie.com/nl/">Mollie-website</a> een account aanmaken voor je organisatie.';
        $out .= 'Dit kan enkel voor organisaties met handelsregisternummer en zakelijk bankaccount. ';
        $out .= '</p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="apikey" class="control-label">';
        $out .= 'Mollie apikey</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-key"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" name="apikey" ';
        $out .= 'value="';
        $out .= $apikey ?? '';
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Kopiëer de apikey die je bekomt van de <a href="https://www.mollie.com/nl/">Mollie website</a>. ';
        $out .= 'Ze moet beginnen met <code>live_</code> of <code>test_</code>. ';
        $out .= '(Met <code>test_</code> kan je enkel testen en geen betalingen ontvangen.)</p>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('mollie_payments', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('mollie_payments');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
