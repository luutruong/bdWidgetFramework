<?php

class WidgetFramework_WidgetGroup extends WidgetFramework_WidgetRenderer
{
    public function getConfiguration()
    {
        $configuration = parent::getConfiguration();

        $configuration['options'] = array(
            'layout' => XenForo_Input::STRING,
        );

        return $configuration;
    }

    public function prepare(array &$widgetRef, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        if (empty($widgetRef['widgets'])) {
            return;
        }

        foreach ($widgetRef['widgets'] as &$subWidgetRef) {
            $renderer = WidgetFramework_Core::getRenderer($subWidgetRef['class'], false);
            if ($renderer) {
                $renderer->prepare($subWidgetRef, $positionCode, $params, $template);
            }
        }
    }

    public function render(
        array &$groupRef,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template,
        &$output)
    {
        if (empty($groupRef['widgets'])) {
            return '';
        }

        $widgetIds = array_keys($groupRef['widgets']);

        $layout = 'rows';
        if (!empty($groupRef['options']['layout'])) {
            switch ($groupRef['options']['layout']) {
                case 'columns':
                case 'random':
                case 'tabs':
                    $layout = $groupRef['options']['layout'];
                    break;
            }
        }

        if (!WidgetFramework_Option::get('layoutEditorEnabled')
            && $layout === 'random'
        ) {
            $randKey = array_rand($widgetIds);
            $widgetIds = array($widgetIds[$randKey]);
        }

        $widgets = array();
        $widgetParams = $params;
        $widgetParams[WidgetFramework_Core::PARAM_PARENT_GROUP_ID] = $groupRef['widget_id'];

        foreach ($widgetIds as $widgetId) {
            $widgetRef =& $groupRef['widgets'][$widgetId];
            $widgetRef['_runtime']['html'] = '';
            $widgetRef['_runtime']['ajaxLoadUrl'] = '';

            $renderer = WidgetFramework_Core::getRenderer($widgetRef['class'], false);
            if (!WidgetFramework_Option::get('layoutEditorEnabled')
                && count($widgets) > 0
                && $layout === 'tabs'
                && !empty($renderer)
                && $renderer->canAjaxLoad($widgetRef)
            ) {
                $widgetRef['_runtime']['ajaxLoadUrl'] = $renderer->getAjaxLoadUrl($widgetRef, $positionCode, $params, $template);
                $widgetRef['_runtime']['html'] = $widgetRef['_runtime']['ajaxLoadUrl'];
            } else {
                $widgetRef['_runtime']['html'] = WidgetFramework_Core::getInstance()->renderWidget($widgetRef,
                    $positionCode, $widgetParams, $template, $widgetRef['_runtime']['html']);
            }

            if (!empty($widgetRef['_runtime']['html'])
                || WidgetFramework_Option::get('layoutEditorEnabled')
            ) {
                $widgets[$widgetId] =& $widgetRef;
            }
        }

        return $this->_wrapWidgets($groupRef, $widgets, $params, $template);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'Group',
            'isHidden' => true,
            'useWrapper' => false,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_group_options';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        // TODO: Implement _getRenderTemplate() method.
    }

    protected function _wrapWidgets(
        array &$groupRef,
        array &$widgetsRef,
        array $params,
        XenForo_Template_Abstract $template)
    {
        $wrapperTemplateName = 'wf_widget_group_wrapper';

        if (WidgetFramework_Option::get('layoutEditorEnabled')) {
            $wrapperTemplateName = 'wf_layout_editor_widget_group_wrapper';

            $params['groupSaveParams'] = array(
                'group_id' => $groupRef['widget_id'],
            );

            $params['conditionalParams'] = WidgetFramework_Template_Helper_Layout::prepareConditionalParams($params);
            if (!empty($groupRef['widget_page_id'])
                && !empty($params['conditionalParams']['widgetPage'])
            ) {
                $params['groupSaveParams']['widget_page_id'] = $groupRef['widget_page_id'];
                unset($params['conditionalParams']['widgetPage']);
            }
        }

        if (!empty($params[self::PARAM_IS_HOOK])) {
            $params['classSection'] = 'widget-container act-as-sidebar sidebar';
        } else {
            $params['classSection'] = '';
        }

        if (XenForo_Template_Helper_Core::styleProperty('wf_groupBorder')
            && empty($groupRef['group_id'])
        ) {
            if (!empty($params[WidgetFramework_Core::PARAM_IS_HOOK])) {
                $params['classSection'] .= ' section sectionMain';
            } else {
                $params['classSection'] .= ' section';
            }
        }

        $wrapperTemplateObj = $template->create($wrapperTemplateName, $params);
        $wrapperTemplateObj->setParam('group', $groupRef);
        $wrapperTemplateObj->setParam('groupId', $groupRef['widget_id']);
        $wrapperTemplateObj->setParam('widgets', $widgetsRef);
        $wrapperTemplateObj->setParam('widgetIds', $widgetsRef);

        return $wrapperTemplateObj;
    }

    protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
    {
        throw new XenForo_Exception('not implemented');
    }
}