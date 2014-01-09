<?php
/**
 * @package ImpressPages
 *
 */
namespace Ip\Internal\Content;

use Ip\WidgetController;

class System
{


    function init()
    {

        $dispatcher = ipDispatcher();

        $dispatcher->addEventListener('Ip.pageRevisionDuplicated', __NAMESPACE__ . '\System::duplicatedRevision');
        $dispatcher->addEventListener('Ip.pageRevisionRemoved', __NAMESPACE__ . '\System::removeRevision');
        $dispatcher->addEventListener('Ip.pageRevisionPublished', __NAMESPACE__ . '\System::publishRevision');
        $dispatcher->addEventListener('Ip.cronExecute', array($this, 'executeCron'));
        $dispatcher->addEventListener('Ip.pageDeleted', __NAMESPACE__ . '\System::pageDeleted');
        $dispatcher->addEventListener('site.pageMoved', __NAMESPACE__ . '\System::pageMoved'); //TODOXX THIS EVENT IS NEVER THROWN #150

        ipAddJs(ipFileUrl('Ip/Internal/Content/assets/widgets.js'));

        $ipUrlOverrides = ipConfig()->getRaw('URL_OVERRIDES');
        if (!$ipUrlOverrides) {
            $ipUrlOverrides = array();
        }

        ipAddJsVariable('ipUrlOverrides', $ipUrlOverrides);

        $dispatcher->addEventListener('Ip.adminLoginSuccessful', array($this, 'adminLogin'));
    }



    public function adminLogin($data)
    {
        Service::setManagementMode(1);
    }


    public function executeCron($info)
    {
        if ($info['firstTimeThisDay'] || $info['test']) {
            Model::deleteUnusedWidgets();
        }
    }

    function findPluginWidgets($moduleName)
    {
        $widgetDir = ipFile('Plugin/' . $moduleName . '/' . Model::WIDGET_DIR . '/');

        if (!is_dir($widgetDir)) {
            return array();
        }
        $widgetFolders = scandir($widgetDir);
        if ($widgetFolders === false) {
            return array();
        }

        $answer = array();
        //foreach all widget folders
        foreach ($widgetFolders as $widgetFolder) {
            //each directory is a widget
            if (!is_dir($widgetDir . $widgetFolder) || $widgetFolder == '.' || $widgetFolder == '..') {
                continue;
            }
            if (isset ($answer[(string)$widgetFolder])) {
                ipLog()->warning(
                    'Content.duplicateWidget: {widget}',
                    array('plugin' => 'Content', 'widget' => $widgetFolder)
                );
            }
            $answer[] = array(
                'module' => $moduleName,
                'dir' => $widgetDir . $widgetFolder . '/',
                'widgetKey' => $widgetFolder
            );
        }
        return $answer;
    }


    public static function duplicatedRevision($info)
    {
        Model::duplicateRevision($info['basedOn'], $info['newRevisionId']);
    }


    public static function removeRevision($info)
    {
        Model::removeRevision($info['revisionId']);
    }

    public static function publishRevision($info)
    {
        Model::clearCache($info['revisionId']);
    }

    public static function pageDeleted($info)
    {
        Model::removePageRevisions($info['zoneName'], $info['pageId']);
    }

    public static function pageMoved($info)
    {
        if ($info['newZoneName'] != $info['oldZoneName']) {
            //move revisions from one zone to another
            Model::updatePageRevisionsZone($info['pageId'], $info['oldZoneName'], $info['newZoneName']);
        }
    }

}


