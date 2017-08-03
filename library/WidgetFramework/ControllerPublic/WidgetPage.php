<?php

class WidgetFramework_ControllerPublic_WidgetPage extends XenForo_ControllerPublic_Abstract
{

    protected function _postDispatch($controllerResponse, $controllerName, $action)
    {
        if (XenForo_Application::isRegistered('nodesAsTabsAPI')) {
            $nodeId = (isset($controllerResponse->params['widgetPage']['node_id']) ? $controllerResponse->params['widgetPage']['node_id'] : 0);

            call_user_func(
                array('NodesAsTabs_API', 'postDispatch'),
                $this,
                $nodeId,
                $controllerResponse,
                $controllerName,
                $action
            );
        }
    }

    public function actionIndex()
    {
        $nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
        $nodeName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
        $widgetPage = $this->_getWidgetPageOrError($nodeId ? $nodeId : $nodeName);

        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $this->canonicalizeRequestUrl(
            XenForo_Link::buildPublicLink('widget-pages', $widgetPage, array('page' => $page))
        );

        $widgetsConditions = array('widget_page_id' => $widgetPage['node_id']);
        if (!WidgetFramework_Option::get('layoutEditorEnabled')) {
            $widgetsConditions['active'] = 1;
        }

        $widgets = $this->_getWidgetModel()->getWidgets($widgetsConditions);

        $nodeBreadCrumbs = $this->_getNodeModel()->getNodeBreadCrumbs($widgetPage, $page > 1);

        $viewParams = array(
            'widgetPage' => $widgetPage,
            'widgets' => $widgets,

            'nodeBreadCrumbs' => $nodeBreadCrumbs,
            'page' => $page,
        );

        $containerParams = array();
        if (!empty($widgetPage['options']['container_template'])) {
            $containerParams['containerTemplate'] = $widgetPage['options']['container_template'];
        }

        if (class_exists('bdCache_ControllerHelper_Cache')) {
            /** @var bdCache_ControllerHelper_Cache $cacheHelper */
            $cacheHelper = $this->getHelper('bdCache_ControllerHelper_Cache');
            $cacheHelper->markViewParamsAsCacheable($viewParams);
        }

        $indexNodeId = WidgetFramework_Option::get('indexNodeId');
        // TODO: should we change section for other node type too?
        $indexChildNodes = WidgetFramework_Helper_Index::getChildNodes();
        if ($widgetPage['node_id'] == $indexNodeId OR isset($indexChildNodes[$widgetPage['node_id']])) {
            $this->_routeMatch->setSections(WidgetFramework_Option::get('indexTabId'));
        }

        return $this->responseView(
            'WidgetFramework_ViewPublic_WidgetPage_View',
            'wf_widget_page',
            $viewParams,
            $containerParams
        );
    }

    public function actionAsIndex()
    {
        $this->_request->setParam('node_id', WidgetFramework_Option::get('indexNodeId'));

        return $this->responseReroute(__CLASS__, 'index');
    }

    public static function getSessionActivityDetailsForList(array $activities)
    {
        $nodeIds = array();
        $nodeNames = array();
        foreach ($activities AS $activity) {
            if (!empty($activity['params']['node_id'])) {
                $nodeIds[$activity['params']['node_id']] = intval($activity['params']['node_id']);
            } else {
                if (!empty($activity['params']['node_name'])) {
                    $nodeNames[$activity['params']['node_name']] = $activity['params']['node_name'];
                }
            }
        }

        if ($nodeNames) {
            /** @var XenForo_Model_Node $nodeModel */
            $nodeModel = XenForo_Model::create('XenForo_Model_Node');
            $nodeNames = $nodeModel->getNodeIdsFromNames($nodeNames);

            foreach ($nodeNames AS $nodeName => $nodeId) {
                $nodeIds[$nodeName] = $nodeId;
            }
        }

        $widgetPageData = array();
        $indexNodeId = WidgetFramework_Option::get('indexNodeId');

        if ($nodeIds) {
            /* @var $widgetPageModel WidgetFramework_Model_WidgetPage */
            $widgetPageModel = XenForo_Model::create('WidgetFramework_Model_WidgetPage');

            $visitor = XenForo_Visitor::getInstance();
            $permissionCombinationId = $visitor['permission_combination_id'];

            $widgetPages = $widgetPageModel->getWidgetPages(
                array('node_id' => $nodeIds),
                array('permissionCombinationId' => $permissionCombinationId)
            );
            foreach ($widgetPages AS $widgetPage) {
                $visitor->setNodePermissions($widgetPage['node_id'], $widgetPage['node_permission_cache']);
                if ($widgetPageModel->canViewWidgetPage($widgetPage)) {
                    $widgetPageData[$widgetPage['node_id']] = array(
                        'title' => $widgetPage['title'],
                        'url' => XenForo_Link::buildPublicLink('widget-pages', $widgetPage)
                    );
                }
            }
        }

        $output = array();
        foreach ($activities AS $key => $activity) {
            $nodeId = 0;
            $widgetPage = false;

            if (!empty($activity['params']['node_id'])) {
                $nodeId = $activity['params']['node_id'];
                if (isset($widgetPageData[$nodeId])) {
                    $widgetPage = $widgetPageData[$nodeId];
                }
            } elseif (!empty($activity['params']['node_name'])) {
                $nodeName = $activity['params']['node_name'];
                if (isset($nodeNames[$nodeName])) {
                    $nodeId = $nodeNames[$nodeName];
                    if (isset($widgetPageData[$nodeId])) {
                        $widgetPage = $widgetPageData[$nodeId];
                    }
                }
            }

            if ($widgetPage) {
                if ($nodeId == $indexNodeId) {
                    $output[$key] = new XenForo_Phrase('wf_viewing_home_page');
                } else {
                    $output[$key] = array(
                        new XenForo_Phrase('wf_viewing_widget_page'),
                        $widgetPage['title'],
                        $widgetPage['url'],
                        false
                    );
                }
            } else {
                $output[$key] = new XenForo_Phrase('wf_viewing_widget_page');
            }
        }

        return $output;
    }

    protected function _getWidgetPageOrError($nodeIdOrName)
    {
        $visitor = XenForo_Visitor::getInstance();
        $fetchOptions = array('permissionCombinationId' => $visitor['permission_combination_id']);

        if (is_numeric($nodeIdOrName)) {
            $widgetPage = $this->_getWidgetPageModel()->getWidgetPageById($nodeIdOrName, $fetchOptions);
        } else {
            $widgetPage = $this->_getWidgetPageModel()->getWidgetPageByName($nodeIdOrName, $fetchOptions);
        }

        if (!$widgetPage
            || $widgetPage['node_type_id'] != 'WF_WidgetPage'
        ) {
            throw $this->responseException(
                $this->responseError(new XenForo_Phrase('wf_requested_widget_page_not_found'), 404)
            );
        }

        if (isset($widgetPage['node_permission_cache'])) {
            $visitor->setNodePermissions($widgetPage['node_id'], $widgetPage['node_permission_cache']);
            unset($widgetPage['node_permission_cache']);
        }

        if (!$this->_getWidgetPageModel()->canViewWidgetPage($widgetPage, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        if ($widgetPage['effective_style_id']) {
            $this->setViewStateChange('styleId', $widgetPage['effective_style_id']);
        }

        return $widgetPage;
    }

    /**
     * @return WidgetFramework_Model_WidgetPage
     */
    protected function _getWidgetPageModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('WidgetFramework_Model_WidgetPage');
    }

    /**
     * @return WidgetFramework_Model_Widget
     */
    protected function _getWidgetModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('WidgetFramework_Model_Widget');
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Node');
    }
}
