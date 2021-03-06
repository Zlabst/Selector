<?php namespace Selector;

include_once (MODX_BASE_PATH . 'assets/snippets/DocLister/lib/DLTemplate.class.php');
include_once (MODX_BASE_PATH . 'assets/lib/APIHelpers.class.php');
require_once (MODX_BASE_PATH . 'assets/lib/Helpers/FS.php');

class Selector {
    public $modx = null;
    protected $fs = null;
    public $DLTemplate = null;
    public $customTvName = 'Selector Custom TV';
    public $tv = array();
    public $tpl = 'assets/tvs/selector/tpl/selector.tpl';
    public $jsListDefault = 'assets/tvs/selector/js/scripts.json';
    public $jsListCustom = 'assets/tvs/selector/js/custom.json';
    public $cssListDefault = 'assets/tvs/selector/css/styles.json';
    public $cssListCustom = 'assets/tvs/selector/css/custom.json';
    
    public function __construct($modx, $tv) {
        $this->modx = $modx;
        $this->tv = $tv;
        $this->DLTemplate = \DLTemplate::getInstance($this->modx);
        $this->fs = \Helpers\FS::getInstance();        
    }

      public function prerender() {
        $output = '';
        $plugins = $this->modx->pluginEvent;
        if((array_search('ManagerManager', $plugins['OnDocFormRender']) === false) && !isset($this->modx->loadedjscripts['jQuery'])) {
            $output .= '<script type="text/javascript" src="'.$this->modx->config['site_url'].'assets/js/jquery/jquery-1.9.1.min.js"></script>';
            $this->modx->loadedjscripts['jQuery'] = array('version'=>'1.9.1');
            $output .='<script type="text/javascript">var jQuery = jQuery.noConflict(true);</script>';
        }
        $tpl = MODX_BASE_PATH.$this->tpl;
        if($this->fs->checkFile($tpl)) {
            $output .= '[+js+][+styles+]'.file_get_contents($tpl);
        } else {
            $this->modx->logEvent(0, 3, "Cannot load {$this->tpl} .", $this->customTvName);
            return false;
        }
        return $output;
    }

    /**
     * @param $list
     * @param array $ph
     * @return string
     */
    public function renderJS($list,$ph = array()) {
        $js = '';
        $scripts = MODX_BASE_PATH.$list;
        if($this->fs->checkFile($scripts)) {
            $scripts = @file_get_contents($scripts);
            $scripts = $this->DLTemplate->parseChunk('@CODE:'.$scripts,$ph);
            $scripts = json_decode($scripts,true);
            $scripts = isset($scripts['scripts']) ? $scripts['scripts'] : $scripts['styles'];
            foreach ($scripts as $name => $params) {
                if (!isset($this->modx->loadedjscripts[$name])) {
                    if ($this->fs->checkFile($params['src'])) {
                        $this->modx->loadedjscripts[$name] = array('version'=>$params['version']);
                        if (end(explode('.',$params['src'])) == 'js') {
                            $js .= '<script type="text/javascript" src="' . $this->modx->config['site_url'] . $params['src'] . '"></script>';
                        } else {
                            $js .= '<link rel="stylesheet" type="text/css" href="'. $this->modx->config['site_url'] . $params['src'] .'">';
                        }
                    } else {
                        $this->modx->logEvent(0, 3, 'Cannot load '.$params['src'], $this->customTvName);
                    }
                }
            }
        } else {
            if ($list == $this->jsListDefault) {
                $this->modx->logEvent(0, 3, "Cannot load {$this->jsListDefault} .", $this->customTvName);
            } elseif ($list == $this->cssListDefault) {
                $this->modx->logEvent(0, 3, "Cannot load {$this->cssListDefault} .", $this->customTvName);
            }
        }
        return $js;
    }

    public function getTplPlaceholders() {
        $ph = array (
            'tv_id'      => $this->tv['id'],
            'tv_value'   => $this->tv['value'],
            'tv_name'    => $this->tv['name'],
            'site_url'      => $this->modx->config['site_url'],
            'values'        => !empty($this->tv['value']) ? $this->modx->runSnippet('DocLister',array(
                'idType'    => 'documents',
                'documents' => $this->tv['value'],
                'showNoPublish'=> 1,
                'sortType'  => 'doclist',
                'tpl'       => '@CODE: <option value="[+id+]" selected>[+id+]. [+pagetitle+]</option>'
            )) : ''
        );
        return $ph;
    }

    /**
     * @return string
     */
    public function render() {
        $output = $this->prerender();
        if ($output !== false) {
           $ph = $this->getTplPlaceholders();
           $ph['js'] = $this->renderJS($this->jsListDefault,$ph) . $this->renderJS($this->jsListCustom,$ph);
           $ph['styles'] = $this->renderJS($this->cssListDefault,$ph) . $this->renderJS($this->cssListCustom,$ph);
           $output = $this->DLTemplate->parseChunk('@CODE:'.$output,$ph);
        }
        return $output;
    }
}