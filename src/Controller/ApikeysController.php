<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\DateFormatService;
use App\Service\PageParamsService;

class ApikeysController extends AbstractController
{
    public function __invoke(
        Db $db,
        MenuService $menu_service,
        HeadingRender $heading_render,
        BtnTopRender $btn_top_render,
        LinkRender $link_render,
        PageParamsService $pp,
        DateFormatService $date_format_service
    ):Response
    {
        $apikeys = $db->fetchAll('select *
            from ' . $pp->schema() . '.apikeys');

        $btn_top_render->add('apikeys_add', $pp->ary(), [], 'Apikey toevoegen');

        $heading_render->add('Apikeys');
        $heading_render->fa('key');

        $out = self::get_apikey_explain();

        $out .= '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-hover table-striped footable">';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th>Id</th>';
        $out .= '<th>Commentaar</th>';
        $out .= '<th data-hide="phone">Apikey</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-initial="true">Gecreëerd</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($apikeys as $a)
        {
            $td = [];
            $td[] = $a['id'];
            $td[] = $a['comment'];
            $td[] = $a['apikey'];
            $td[] = $date_format_service->get_td($a['created'], 'min', $pp->schema());
            $td[] = $link_render->link_fa('apikeys_del', $pp->ary(),
                ['id' => $a['id']], 'Verwijderen',
                ['class' => 'btn btn-danger'], 'times');

            $out .= '<tr><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $menu_service->set('apikeys');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function get_apikey_explain():string
    {
        $out = '<p>';
        $out .= '<ul>';
        $out .= '<li>';
        $out .= 'Apikeys zijn enkel nodig voor het leggen van ';
        $out .= 'interSysteem verbindingen naar andere Systemen die ';
        $out .= 'eLAS draaien.</li>';
        $out .= '<li>Voor het leggen van interSysteem ';
        $out .= 'verbindingen naar andere Systemen op ';
        $out .= 'deze eLAND-server ';
        $out .= 'moet je geen Apikey aanmaken.';
        $out .= '</li></ul>';
        $out .= '</p>';
        return $out;
    }
}
