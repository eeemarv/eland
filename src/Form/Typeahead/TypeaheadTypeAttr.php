<?php declare(strict_types=1);

namespace App\Form\Typeahead;

use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\Exception\InvalidArgumentException;

class TypeaheadTypeAttr
{
    protected TypeaheadService $typeahead_service;
    protected LinkRender $link_render;
    protected PageParamsService $pp;

    public function __construct(
        TypeaheadService $typeahead_service,
        LinkRender $link_render ,
        PageParamsService $pp
    )
    {
        $this->typeahead_service = $typeahead_service;
        $this->link_render = $link_render;
        $this->pp = $pp;
    }

    public function get(array $options):array
    {
        $attr = [];

        if (isset($options['source_route']))
        {
            if (isset($options['source_id']))
            {
                throw new InvalidArgumentException(
                    'options "source_route" and "source_id" can
                    not be both set in %s'.  __CLASS__);
            }

            if (isset($options['source']))
            {
                throw new InvalidArgumentException(
                    'options "source_route" and "source" can
                    not be both set in %s' . __CLASS__);
            }

            $source = ['route' => $options['source_route']];

            if (isset($options['source_params']))
            {
                if (!is_array($options['source_params']))
                {
                    throw new InvalidArgumentException(
                        'option "source_params" must be an
                        array in ' .  __CLASS__);
                }

                $source['params'] = $options['source_params'];
            }

            $options['source'] = [$source];
        }

        if (isset($options['source_id']))
        {
            $attr['data-typeahead-source-id'] = $options['source_id'];
        }
        else if (isset($options['source']))
        {
            if (isset($options['data_path']))
            {
                throw new InvalidArgumentException(
                    'options "data_path" and "data_source" can
                    not be both set in ' . __CLASS__);
            }

            $source = $options['source'];

            if (!is_array($source))
            {
                throw new InvalidArgumentException(
                    'option "source" must be an
                    array in ' . __CLASS__);
            }

            $dataTypeahead = [];
            $baseParams = [
                'schema'    => $this->schema,
                'access'    => $this->access,
            ];

            foreach ($source as $s)
            {
                if (!isset($s['route']))
                {
                    throw new InvalidArgumentException(
                        'a "route" key is missing from option "source"
                        in ' . __CLASS__);
                }

                $params = isset($s['params']) && is_array($s['params']) ? $s['params'] : [];
                $params = array_merge($params, $baseParams);

                $path = $this->urlGenerator->generate($s['route'], $params);

                $dataTypeahead[] = [
                    'path'          => $path,
                    'thumbprint'    => $this->thumbprint->get($path),
                ];
            }

            $attr['data-typeahead'] = json_encode($dataTypeahead);
        }
        else
        {
            throw new InvalidArgumentException(
                'either "data-source" of "source" option needs
                to be set for the typeahead type in ' .  __CLASS__);
        }

        if (isset($options['process']))
        {
            $attr['data-typeahead-process'] = $options['process'];
        }

        return $attr;
    }
}