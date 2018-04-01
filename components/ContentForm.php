<?php

namespace Impala;

use Nette\Application\UI\Control,
    Nette\Application\IPresenter,
    Nette\Application\Responses\JsonResponse,
    Nette\ComponentModel\IComponent,
    Nette\Database\Table\IRow,
    Nette\Localization\ITranslator;

/** @author Lubomir Andrisek */
final class ContentForm extends Control implements IContentFormFactory {

    /** @var IBuilder */
    private $builder;

    /** @var IContent */
    private $contentRepository;

    /** @var string */
    private $jsDir;

    /** @var string */
    private $keyword;

    /** @var KeywordsRepository */
    private $keywordsRepository;

    /** @var IPresenter */
    private $presenter;

    /** @var IRow */
    private $row;

    /** @var array */
    private $source;

    /** @var ITranslator */
    private $translatorRepository;

    /** @var array */
    private $used = [];

    /** @var WriteRepository */
    private $writeRepository;

    public function __construct($jsDir, IContent $contentRepository, IBuilder $builder, ITranslator $translatorRepository,
        KeywordsRepository $keywordsRepository, WriteRepository $writeRepository) {
        $this->jsDir = $jsDir;
        $this->contentRepository = $contentRepository;
        $this->writeRepository = $writeRepository;
        $this->builder = $builder;
        $this->keywordsRepository = $keywordsRepository;
        $this->translatorRepository = $translatorRepository;
    }

    public function create(): IContentFormFactory {
        return $this;
    }

    public function attached(IComponent $presenter): void {
        parent::attached($presenter);
        if($presenter instanceof IPresenter) {
            $this->presenter = $presenter;
            $this->keyword = $this->builder->getQuery('keyword');
        }
    }

    public function handleKeyword(): void {
        $response = new JsonResponse($this->wildcard($this->builder->getPost('keywords'), []));
        $this->presenter->sendResponse($response);
    }

    public function handleSubmit(): void  {
        $this->writeRepository->updateWrite($this->keyword, ['content' => $this->builder->getPost('content')]);
        $this->presenter->sendPayload();
    }

    public function handleWrite(): void {
        $keywords = $this->builder->getPost('keywords');
        $options = $this->builder->getPost('options');
        $wildcards = $this->builder->getPost('wildcards');
        $write = $this->builder->getPost('write');
        if(empty($this->used = $this->builder->getPost('used'))) {
            $this->used = [];
        }
        $max = 0;
        /** solved by name */
        foreach ($options as $optionId => $option) {
            $summary = substr_count($keywords, $option);
            if ($summary > $max) {
                $max = $summary;
                $selected = $optionId;
            }
        }
        /** solved by database */
        $summaries = [];
        $like = $wildcards;
        if (0 == $max) {
            foreach ($options as $optionId => $option) {
                $summaries[$option] = 0;
                if(sizeof($like = $this->wildcard($option, $wildcards)) > sizeof($wildcards)) {
                    $summaries[$option] = $summary = $this->contentRepository->getKeywords($like);
                    if ($summary > $max) {
                        $final = $like;
                        $max = $summary;
                        $selected = $optionId;
                    }
                }
            }
        }
        if (!isset($selected)) {
            $selected = array_rand($options);
        }
        $this->used[$options[$selected]] = $options[$selected];
        $response = new JsonResponse(['keywords' => $keywords, 'option' => $selected, 'options' => $options, 'max' => $max, 'summary' => $summaries,
            'wildcards' => isset($final) ? $final : $like, 'used' => $this->used]);
        $this->presenter->sendResponse($response);
    }

    public function render(...$args): void {
        $this->template->component = $this->getName();
        $this->template->data =  json_encode(['content' => json_decode(trim($this->row->check()->content)),
                                            'current' => 0,
                                            'labels'=>['select'=>ucfirst($this->translatorRepository->translate('select')),
                                                        'plain'=>ucfirst($this->translatorRepository->translate('plain')),
                                                        'submit'=>ucfirst($this->translatorRepository->translate(1124.0)),
                                                        'statistics'=>ucfirst($this->translatorRepository->translate('statistics')),
                                                        'write'=>ucfirst($this->translatorRepository->translate('output'))],
                                            'source' => $this->source]);
        $this->template->links =  json_encode(['keyword' => $this->link('keyword'),'submit' => $this->link('submit'),'write' => $this->link('write')]);
        $this->template->js = $this->getPresenter()->template->basePath . '/' . $this->jsDir;
        $this->template->setFile(__DIR__ . '/../templates/content.latte');
        $this->template->render();
    }

    public function setRow(IRow $row): IContentFormFactory {
        $this->row = $row;
        return $this;
    }

    public function setSource(array $source): IContentFormFactory {
        $this->source = $source;
        return $this;
    }

    private function wildcard(string $option, array $wildcards): array {
        foreach (explode(' ', trim(preg_replace('/\s+|\.|\,/', ' ', $option))) as $wildcard) {
            $row = $this->keywordsRepository->getKeyword($wildcard, $this->used);
            if ($row instanceof ActiveRow && !in_array($wildcard, $wildcards) && !empty($wildcard) && strlen($wildcard) > 2) {
                foreach(json_decode($row->content) as $content) {
                    $wildcards[$wildcard][] = '% ' . $content . ' %';
                    $wildcards[$wildcard][] = '% ' . $content . '. %';
                    $wildcards[$wildcard][] = '% ' . $content . ', %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_LOWER, 'UTF-8') . ' %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_TITLE, 'UTF-8') . ' %';

                    $wildcards[$wildcard][] = '% ' . $content . ' %';
                    $wildcards[$wildcard][] = '% ' . $content . '. %';
                    $wildcards[$wildcard][] = '% ' . $content . ', %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_LOWER, 'UTF-8') . ' %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_TITLE, 'UTF-8') . ' %';

                    $wildcards[$wildcard][] = '% ' . $content . ' %';
                    $wildcards[$wildcard][] = '% ' . $content . '. %';
                    $wildcards[$wildcard][] = '% ' . $content . ', %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_LOWER, 'UTF-8') . ' %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_TITLE, 'UTF-8') . ' %';

                    $wildcards[$wildcard][] = '% ' . $content . ' %';
                    $wildcards[$wildcard][] = '% ' . $content . '. %';
                    $wildcards[$wildcard][] = '% ' . $content . ', ';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_LOWER, 'UTF-8') . ' %';
                    $wildcards[$wildcard][] = '% ' . mb_convert_case($content, MB_CASE_TITLE, 'UTF-8') . ' %';
                }
            }
       }
       return $wildcards;
    }

}

interface IContentFormFactory {

    public function create(): IContentFormFactory;
}
