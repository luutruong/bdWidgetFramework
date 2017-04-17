<?php

abstract class WidgetFramework_WidgetRenderer
{
    // these constants are kept here for compatibility only
    // please use constants from WidgetFramework_Core from now on
    const PARAM_TO_BE_PROCESSED = '_WidgetFramework_toBeProcessed';
    const PARAM_POSITION_CODE = '_WidgetFramework_positionCode';
    const PARAM_IS_HOOK = '_WidgetFramework_isHook';
    const PARAM_IS_GROUP = '_WidgetFramework_isGroup';
    const PARAM_GROUP_NAME = '_WidgetFramework_groupId';
    const PARAM_PARENT_GROUP_NAME = '_WidgetFramework_parentGroupId';
    const PARAM_PARENT_TEMPLATE = '_WidgetFramework_parentTemplate';
    const PARAM_TEMPLATE_OBJECTS = '_WidgetFramework_templateObjects';
    // please use constants from WidgetFramework_Core from now on

    /**
     * Returns renderer configuration array.
     * Available config keys:
     *    - name: Display name to be used in renderer selection list.
     *    - isHidden: Flag to hide the renderer when creating new widget. Default `false`.
     *    - options: An array of renderer's options. Default `array()`.
     *    - useCache: Flag to determine the renderer can be cached or not. Default `false`.
     *    - useUserCache: Flag to determine the renderer needs to be cached by an
     *                      user-basis. Internally, this is implemented by getting the current user
     *                      permission combination id (not the user id as normally expected). This is
     *                      done to make sure the cache is used effectively. Default `false`.
     *    - cacheSeconds: A numeric value to specify the maximum age of the cache (in seconds).
     *                      If the cache is too old, the widget will be rendered from scratch. Default `0`.
     *    - useWrapper: Flag to determine the widget should be wrapped with a wrapper. Default `true`.
     *    - canAjaxLoad: Flag to determine the widget can be loaded via ajax. Default `false`.
     *
     * @return array
     */
    abstract protected function _getConfiguration();

    /**
     * Returns title of the options admin-template.
     * Or returns false if it's not available.
     *
     * @return string|false
     */
    abstract protected function _getOptionsTemplate();

    /**
     * Returns title of the render public-template.
     *
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @return string
     */
    abstract protected function _getRenderTemplate(array $widget, $positionCode, array $params);

    /**
     * Prepares data for the render template.
     *
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     */
    abstract protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    );

    protected function _renderOptions(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Template_Abstract $template
    ) {
        return true;
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if ($optionKey === 'cache_seconds') {
            if (!is_numeric($optionValue)) {
                $optionValue = '';
            } elseif ($optionValue < 0) {
                $optionValue = 0;
            }
        } elseif ($optionKey === 'conditional') {
            $raw = '';
            if (!empty($optionValue['raw'])) {
                $raw = $optionValue['raw'];
            }

            if (!empty($raw)) {
                $optionValue = array(
                    'raw' => $raw,
                    'parsed' => WidgetFramework_Helper_Conditional::parse($raw),
                );
            } else {
                $optionValue = array();
            }
        }

        return true;
    }

    protected function _prepare(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        return true;
    }

    protected function _getExtraDataLink(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        return false;
    }

    /**
     * Helper method to prepare source array for <xen:select /> or similar tags
     *
     * @param array $selected an array of selected values
     * @param bool $useSpecialForums flag to determine the usage of special forum
     * indicator
     * @return array
     */
    protected function _helperPrepareForumsOptionSource(array $selected = array(), $useSpecialForums = false)
    {
        $forums = array();
        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');
        $nodes = $nodeModel->getAllNodes();

        if ($useSpecialForums) {
            // new XenForo_Phrase('wf_current_forum')
            // new XenForo_Phrase('wf_current_forum_and_children')
            // new XenForo_Phrase('wf_parent_forum')
            // new XenForo_Phrase('wf_parent_forum_and_children')
            foreach (array(
                         self::FORUMS_OPTION_SPECIAL_CURRENT,
                         self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN,
                         self::FORUMS_OPTION_SPECIAL_PARENT,
                         self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN,
                     ) as $specialId) {
                $forums[] = array(
                    'value' => $specialId,
                    'label' => new XenForo_Phrase(sprintf('wf_%s', $specialId)),
                    'selected' => in_array($specialId, $selected),
                );
            }
        }

        foreach ($nodes as $node) {
            if (in_array($node['node_type_id'], array(
                'Category',
                'LinkForum',
                'Page',
                'WF_WidgetPage'
            ))) {
                continue;
            }

            $forums[] = array(
                'value' => $node['node_id'],
                'label' => str_repeat('--', $node['depth']) . ' ' . $node['title'],
                'selected' => in_array($node['node_id'], $selected),
            );
        }

        return $forums;
    }

    /**
     * Helper method to look for special forum ids in an array of forum ids
     *
     * @param array $forumIds
     * @return bool
     */
    protected function _helperDetectSpecialForums($forumIds)
    {
        if (!is_array($forumIds)) {
            return false;
        }

        foreach ($forumIds as $forumId) {
            switch ($forumId) {
                case self::FORUMS_OPTION_SPECIAL_CURRENT:
                case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
                case self::FORUMS_OPTION_SPECIAL_PARENT:
                case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
                    return true;
            }
        }

        return false;
    }

    /**
     * Helper method to be used within _getCacheId.
     *
     * @param array $forumsOption the `forums` option
     * @param array $templateParams depending on the option, this method
     *                requires information from the template params.
     * @param bool $asGuest flag to use guest permissions instead of
     *                current user permissions
     *
     * @return string forum id or empty string
     */
    protected function _helperGetForumIdForCache(
        array $forumsOption,
        array $templateParams = array(),
        /** @noinspection PhpUnusedParameterInspection */
        $asGuest = false
    ) {
        if (!empty($forumsOption)) {
            $templateNode = null;

            if (!empty($templateParams['forum']['node_id'])) {
                $templateNode = $templateParams['forum'];
            } elseif (!empty($templateParams['category']['node_id'])) {
                $templateNode = $templateParams['category'];
            } elseif (!empty($templateParams['page']['node_id'])) {
                $templateNode = $templateParams['page'];
            } elseif (!empty($templateParams['widgetPage']['node_id'])) {
                $templateNode = $templateParams['widgetPage'];
            }

            if (!empty($templateNode)) {
                return $templateNode['node_id'];
            }
        }

        return '';
    }

    /**
     * Helper method to get an array of forum ids ready to be used.
     * The forum ids are taken after processing the `forums` option.
     * Look into the source code of built-in renderer to understand
     * how to use this method.
     *
     * @param array $forumsOption the `forums` option
     * @param array $templateParams depending on the option, this method
     *                requires information from the template params.
     * @param bool $asGuest flag to use guest permissions instead of
     *                current user permissions
     *
     * @return array of forum ids
     */
    protected function _helperGetForumIdsFromOption(
        array $forumsOption,
        array $templateParams = array(),
        $asGuest = false
    ) {
        if (empty($forumsOption)) {
            $forumIds = array_keys($this->_helperGetViewableNodeList($asGuest));
        } else {
            $forumIds = array_values($forumsOption);
            $forumIdsSpecial = array();
            $templateNode = null;

            if (!empty($templateParams['forum']['node_id'])) {
                $templateNode = $templateParams['forum'];
            } elseif (!empty($templateParams['category']['node_id'])) {
                $templateNode = $templateParams['category'];
            } elseif (!empty($templateParams['page']['node_id'])) {
                $templateNode = $templateParams['page'];
            } elseif (!empty($templateParams['widgetPage']['node_id'])) {
                $templateNode = $templateParams['widgetPage'];
            }

            foreach (array_keys($forumIds) as $i) {
                switch ($forumIds[$i]) {
                    case self::FORUMS_OPTION_SPECIAL_CURRENT:
                        if (!empty($templateNode)) {
                            $forumIdsSpecial[] = $templateNode['node_id'];
                        }
                        unset($forumIds[$i]);
                        break;
                    case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
                        if (!empty($templateNode)) {
                            $templateNodeId = $templateNode['node_id'];
                            $forumIdsSpecial[] = $templateNodeId;

                            $viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
                            $this->_helperMergeChildForumIds($forumIdsSpecial, $viewableNodeList, $templateNodeId);
                        }
                        unset($forumIds[$i]);
                        break;
                    case self::FORUMS_OPTION_SPECIAL_PARENT:
                        if (!empty($templateNode)) {
                            $forumIdsSpecial[] = $templateNode['parent_node_id'];
                        }
                        unset($forumIds[$i]);
                        break;
                    case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
                        if (!empty($templateNode)) {
                            $templateNodeId = $templateNode['parent_node_id'];
                            $forumIdsSpecial[] = $templateNodeId;

                            $viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
                            $this->_helperMergeChildForumIds($forumIdsSpecial, $viewableNodeList, $templateNodeId);
                        }
                        unset($forumIds[$i]);
                        break;
                }
            }

            if (!empty($forumIdsSpecial)) {
                // only merge 2 arrays if some new ids are found...
                $forumIds = array_unique(array_merge($forumIds, $forumIdsSpecial));
            }
        }

        sort($forumIds);

        return $forumIds;
    }

    /**
     * Helper method to traverse a list of nodes looking for
     * children forums of a specified node
     *
     * @param array $forumIds the result array (this array will be modified)
     * @param array $nodes the nodes array to process
     * @param int $parentNodeId the parent node id to use and check against
     */
    protected function _helperMergeChildForumIds(array &$forumIds, array &$nodes, $parentNodeId)
    {
        foreach ($nodes as $node) {
            if ($node['parent_node_id'] == $parentNodeId) {
                $forumIds[] = $node['node_id'];
                $this->_helperMergeChildForumIds($forumIds, $nodes, $node['node_id']);
            }
        }
    }

    /**
     * Helper method to get viewable node list. Renderers need this information
     * should use call this method to get it. The node list is queried and cached
     * to improve performance.
     *
     * @param bool $asGuest flag to use guest permissions instead of current user
     * permissions
     *
     * @return array of viewable node (node_id as array key)
     */
    protected function _helperGetViewableNodeList($asGuest)
    {
        if ($asGuest) {
            return $this->_helperGetViewableNodeListGuestOnly();
        }

        static $viewableNodeList = false;

        if ($viewableNodeList === false) {
            /** @var XenForo_Model_Node $nodeModel */
            $nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');
            $viewableNodeList = $nodeModel->getViewableNodeList();
        }

        return $viewableNodeList;
    }

    protected function _helperGetViewableNodeListGuestOnly()
    {
        static $viewableNodeList = false;

        if ($viewableNodeList === false) {
            /* @var $nodeModel XenForo_Model_Node */
            $nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');

            $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(1);
            $viewableNodeList = $nodeModel->getViewableNodeList($nodePermissions);
        }

        return $viewableNodeList;
    }

    protected $_configuration = false;

    public function getConfiguration()
    {
        if ($this->_configuration === false) {
            $default = array(
                'name' => get_class($this),
                'isHidden' => false,
                'options' => array(),

                'useCache' => false,
                'useUserCache' => false,
                'cacheSeconds' => 0,

                'useWrapper' => true,

                'canAjaxLoad' => false,
            );

            $this->_configuration = XenForo_Application::mapMerge($default, $this->_getConfiguration());

            if ($this->_configuration['useCache']) {
                $this->_configuration['options']['cache_seconds'] = XenForo_Input::STRING;
            }

            // `expression` has been deprecated, use `conditional` instead
            $this->_configuration['options']['expression'] = XenForo_Input::STRING;
            $this->_configuration['options']['conditional'] = XenForo_Input::ARRAY_SIMPLE;

            // `deactivate_for_mobile` has been deprecated,
            // use `conditional` with {$visitorIsBrowsingWithMobile} instead
            $this->_configuration['options']['deactivate_for_mobile'] = XenForo_Input::UINT;
        }

        return $this->_configuration;
    }

    public function getName()
    {
        $configuration = $this->getConfiguration();
        return $configuration['name'];
    }

    public function isHidden()
    {
        $configuration = $this->getConfiguration();
        return !empty($configuration['isHidden']);
    }

    public function useWrapper(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        $configuration = $this->getConfiguration();
        return !empty($configuration['useWrapper']);
    }

    public function useCache(array $widget)
    {
        if (WidgetFramework_Core::debugMode()
            || WidgetFramework_Option::get('layoutEditorEnabled')
            || WidgetFramework_Option::get('cacheStore') === '0'
        ) {
            return false;
        }

        if (isset($widget['options']['cache_seconds'])
            && $widget['options']['cache_seconds'] === '0'
        ) {
            return false;
        }

        if (isset($widget['_ajaxLoadParams'])) {
            return false;
        }

        $configuration = $this->getConfiguration();
        $useCache = !empty($configuration['useCache']);

        if (!$useCache) {
            return false;
        }

        $config = XenForo_Application::getConfig();
        if (XenForo_Visitor::getInstance()->get('is_admin')
            && !$config->get(WidgetFramework_Core::CONFIG_CACHE_ADMIN)
        ) {
            return false;
        }

        if ($this->useUserCache($widget)
            && !$config->get(WidgetFramework_Core::CONFIG_CACHE_ALL_PERMISSION_COMBINATIONS)
        ) {
            $permissionCombinationId = XenForo_Visitor::getInstance()->get('permission_combination_id');
            if (!WidgetFramework_Helper_PermissionCombination::isGroupOnly($permissionCombinationId)) {
                return false;
            }
        }

        return true;
    }

    public function useUserCache(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        $configuration = $this->getConfiguration();
        return !empty($configuration['useUserCache']);
    }

    public function canAjaxLoad(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        $configuration = $this->getConfiguration();
        return !empty($configuration['canAjaxLoad']);
    }

    public function requireLock(array $widget)
    {
        return $this->useCache($widget);
    }

    public function renderOptions(XenForo_ViewRenderer_Abstract $viewRenderer, array &$templateParams)
    {
        $templateParams['namePrefix'] = self::getNamePrefix();
        $templateParams['options_loaded'] = get_class($this);
        $templateParams['options'] = (!empty($templateParams['widget']['options'])) ? $templateParams['widget']['options'] : array();
        $templateParams['rendererConfiguration'] = $this->getConfiguration();

        if ($this->_getOptionsTemplate()) {
            $optionsTemplate = $viewRenderer->createTemplateObject($this->_getOptionsTemplate(), $templateParams);

            $this->_renderOptions($optionsTemplate);

            $templateParams['optionsRendered'] = $optionsTemplate->render();
        }
    }

    public function parseOptionsInput(XenForo_Input $input, array $widget)
    {
        $configuration = $this->getConfiguration();
        $options = empty($widget['options']) ? array() : $widget['options'];

        foreach ($configuration['options'] as $optionKey => $optionType) {
            $optionValue = $input->filterSingle(self::getNamePrefix() . $optionKey, $optionType);
            if ($this->_validateOptionValue($optionKey, $optionValue) !== false) {
                $options[$optionKey] = $optionValue;
            }
        }

        if (!empty($options['conditional'])
            && !empty($options['expression'])
        ) {
            unset($options['expression']);
        }

        return $options;
    }

    public function prepare(array &$widgetRef, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        $template->preloadTemplate('wf_widget_wrapper');

        $renderTemplate = $this->_getRenderTemplate($widgetRef, $positionCode, $params);
        if (!empty($renderTemplate)) {
            $template->preloadTemplate($renderTemplate);
        }

        if ($this->useCache($widgetRef)) {
            $cacheId = $this->_getCacheId($widgetRef, $positionCode, $params);
            $this->_getCacheModel()->preloadCache($cacheId);
        }

        $this->_prepare($widgetRef, $positionCode, $params, $template);
    }

    /**
     * @param $expression
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function _executeExpression($expression, array $params)
    {
        if (WidgetFramework_Core::debugMode()) {
            XenForo_Error::logError('Widget Expression has been deprecated: %s', $expression);
        }

        $expression = trim($expression);
        if (empty($expression)) {
            return true;
        }

        $sandbox = @create_function('$params', 'extract($params); return (' . $expression . ');');

        if (!empty($sandbox)) {
            return call_user_func($sandbox, $params);
        } else {
            throw new Exception('Syntax error');
        }
    }

    protected function _testConditional(array $widget, array $params)
    {
        if (isset($widget['_ajaxLoadParams'])) {
            // ignore for ajax load, it should be tested before the tab is rendered
            // there is a small security risk here but nothing too serious
            return true;
        }

        if (!empty($widget['options']['conditional'])) {
            $conditional = $widget['options']['conditional'];

            if (!empty($conditional['raw'])
                && !empty($conditional['parsed'])
            ) {
                return WidgetFramework_Helper_Conditional::test($conditional['raw'], $conditional['parsed'], $params);
            }
        } elseif (!empty($widget['options']['expression'])) {
            // legacy support
            return $this->_executeExpression($widget['options']['expression'], $params);
        }

        return true;
    }

    protected function _getCacheId(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        array $suffix = array()
    ) {
        $parts = array($positionCode);

        $visitor = XenForo_Visitor::getInstance();
        $xenOptions = XenForo_Application::getOptions();

        if ($this->useUserCache($widget)) {
            $parts[] = sprintf('pc%d', $visitor->get('permission_combination_id'));
        }

        if (isset($params['visitorStyle'])
            && intval($params['visitorStyle']['style_id']) !== intval($xenOptions->get('defaultStyleId'))
        ) {
            $parts[] = sprintf('vs%d', $params['visitorStyle']['style_id']);
        }

        if (isset($params['visitorLanguage'])
            && intval($params['visitorLanguage']['language_id']) !== intval($xenOptions->get('defaultLanguageId'))
        ) {
            $parts[] = sprintf('vl%d', $params['visitorLanguage']['language_id']);
        }

        $visitorTimezone = strval($visitor['timezone']);
        if ($visitorTimezone !== ''
            && $visitorTimezone !== strval($xenOptions->get('guestTimeZone'))
        ) {
            $parts[] = sprintf('vt%s', $visitorTimezone);
        }

        if ($visitor->isBrowsingWith('mobile')) {
            $parts[] = 'vm';
        }

        if (!empty($suffix)) {
            $parts[] = 's' . implode('_', $suffix);
        }

        return implode('_', $parts);
    }

    protected function _restoreFromCache($cached, &$html, &$containerData, &$requiredExternals)
    {
        if (strlen($cached[WidgetFramework_Model_Cache::KEY_HTML]) === 0) {
            return;
        }

        $html = sprintf('<!-- %2$s -->%1$s<!-- /%2$s (%3$ds) -->',
            $cached[WidgetFramework_Model_Cache::KEY_HTML],
            md5($cached[WidgetFramework_Model_Cache::KEY_HTML]),
            XenForo_Application::$time - $cached[WidgetFramework_Model_Cache::KEY_TIME]
        );

        if (!empty($cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_CONTAINER_DATA])) {
            $containerData = $cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_CONTAINER_DATA];
        }

        if (!empty($cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_REQUIRED_EXTERNALS])) {
            $requiredExternals = $cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_REQUIRED_EXTERNALS];
        }
    }

    public function render(
        array &$widgetRef,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template,
        /** @noinspection PhpUnusedParameterInspection */
        &$output
    ) {
        $html = false;
        $containerData = array();
        $requiredExternals = array();

        try {
            if (!$this->_testConditional($widgetRef, $params)) {
                // expression failed, stop rendering...
                if (WidgetFramework_Option::get('layoutEditorEnabled')) {
                    $html = new XenForo_Phrase('wf_layout_editor_widget_conditional_failed');
                } else {
                    $html = '';
                }
            }
        } catch (Exception $e) {
            // problem while testing conditional, stop rendering...
            if (WidgetFramework_Core::debugMode() OR WidgetFramework_Option::get('layoutEditorEnabled')) {
                $html = $e->getMessage();
            } else {
                $html = '';
            }
        }

        // add check for mobile (user agent spoofing)
        // since 2.2.2
        if (!empty($widgetRef['options']['deactivate_for_mobile'])
            && XenForo_Visitor::isBrowsingWith('mobile')
        ) {
            // legacy support
            $html = '';
        }

        // check for cache
        // since 1.2.1
        $cacheId = false;
        $lockId = null;

        if ($html === false
            && $this->useCache($widgetRef)
        ) {
            $cacheId = $this->_getCacheId($widgetRef, $positionCode, $params);
            $cached = $this->_getCacheModel()->getCache($widgetRef['widget_id'], $cacheId);

            if (!empty($cached)
                && is_array($cached)
            ) {
                if ($this->isCacheUsable($cached, $widgetRef)) {
                    // found fresh cached html, use it asap
                    $this->_restoreFromCache($cached, $html, $containerData, $requiredExternals);
                } else {
                    // cached html has expired: try to acquire lock
                    $lockId = $this->_getCacheModel()->acquireLock($widgetRef['widget_id'], $cacheId);

                    if ($lockId === false) {
                        // a lock cannot be acquired, an expired cached html is the second best choice
                        $this->_restoreFromCache($cached, $html, $containerData, $requiredExternals);
                    }
                }
            } else {
                // no cache found
                $lockId = $this->_getCacheModel()->acquireLock($widgetRef['widget_id'], $cacheId);
            }
        }

        if ($html === false
            && $lockId === false
        ) {
            // a lock is required but we failed to acquired it
            // also, a cached could not be found
            // stop rendering
            $html = '';
        }

        // conditional executed just fine
        if ($html === false) {
            $renderTemplate = $this->_getRenderTemplate($widgetRef, $positionCode, $params);
            if (!empty($renderTemplate)) {
                $renderTemplateParams = $params;
                $renderTemplateParams['widget'] =& $widgetRef;
                $renderTemplateObject = $template->create($renderTemplate, $renderTemplateParams);
                $renderTemplateObject->setParam(WidgetFramework_Core::PARAM_CURRENT_WIDGET_ID, $widgetRef['widget_id']);

                $existingExtraContainerData = WidgetFramework_Template_Extended::WidgetFramework_getExtraContainerData();
                WidgetFramework_Template_Extended::WidgetFramework_setExtraContainerData(array());
                $existingRequiredExternals = WidgetFramework_Template_Extended::WidgetFramework_getRequiredExternals();
                WidgetFramework_Template_Extended::WidgetFramework_setRequiredExternals(array());

                $html = $this->_render($widgetRef, $positionCode, $params, $renderTemplateObject);

                if ($cacheId !== false) {
                    // force render template (if any) to collect required externals
                    // only do that if caching is enabled though
                    $html = strval($html);
                }

                // get container data (using template_post_render listener)
                $containerData = WidgetFramework_Template_Extended::WidgetFramework_getExtraContainerData();
                WidgetFramework_Template_Extended::WidgetFramework_setExtraContainerData($existingExtraContainerData);
                // get widget required externals
                $requiredExternals = WidgetFramework_Template_Extended::WidgetFramework_getRequiredExternals();
                WidgetFramework_Template_Extended::WidgetFramework_setRequiredExternals($existingRequiredExternals);
            } else {
                $html = $this->_render($widgetRef, $positionCode, $params, $template);
            }
            $html = trim($html);

            if ($cacheId !== false) {
                $extraData = array();
                if (!empty($containerData)) {
                    $extraData[self::EXTRA_CONTAINER_DATA] = $containerData;
                }
                if (!empty($requiredExternals)) {
                    $extraData[self::EXTRA_REQUIRED_EXTERNALS] = $requiredExternals;
                }

                $this->_getCacheModel()->setCache($widgetRef['widget_id'], $cacheId, $html, $extraData, array(
                    WidgetFramework_Model_Cache::OPTION_LOCK_ID => $lockId,
                ));
            }
        }

        $this->_getCacheModel()->releaseLock($lockId);

        if (!empty($containerData)) {
            // apply container data
            WidgetFramework_Template_Extended::WidgetFramework_mergeExtraContainerData($containerData);
        }

        if (!empty($requiredExternals)) {
            // register required external
            foreach ($requiredExternals as $type => $requirements) {
                foreach ($requirements as $requirement) {
                    $template->addRequiredExternal($type, $requirement);
                }
            }
        }

        return $html;
    }

    public function getAjaxLoadUrl(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        $ajaxLoadParams = $this->_getAjaxLoadParams($widget, $positionCode, $params, $template);
        return WidgetFramework_Helper_AjaxLoadParams::buildLink($widget['widget_id'], $ajaxLoadParams);
    }

    protected function _getAjaxLoadParams(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        $ajaxLoadParams = array();

        if (isset($widget['_ajaxLoadParams'])
            && is_array($widget['_ajaxLoadParams'])
        ) {
            $ajaxLoadParams = $widget['_ajaxLoadParams'];
        } else {
            $ajaxLoadParams[self::PARAM_IS_HOOK] = !empty($params[self::PARAM_IS_HOOK]);
        }

        return $ajaxLoadParams;
    }

    public function extraPrepare(
        array $widget,
        /** @noinspection PhpUnusedParameterInspection */
        &$html
    ) {
        $extra = array();

        $link = $this->_getExtraDataLink($widget);
        if (!empty($link)) {
            $extra['link'] = $link;
        }

        return $extra;
    }

    public function extraPrepareTitle(array $widget)
    {
        if (!empty($widget['title'])) {
            if (is_string($widget['title'])
                && preg_match('/^{xen:phrase ([^}]+)}$/i', $widget['title'], $matches)
            ) {
                // {xen:phrase title} as widget title, use the specified phrase

                if (XenForo_Application::debugMode()) {
                    // this kind of usage is deprecated, log server error entry if debug mode is on
                    XenForo_Error::logError(sprintf(
                        'Widget title support for {xen:phrase title} has been deprecated. '
                        . 'Please update widget #%d.', $widget['widget_id']
                    ));
                }

                return new XenForo_Phrase($matches[1]);
            } else {
                if (!empty($widget['options'][WidgetFramework_DataWriter_Widget::WIDGET_OPTION_ADDON_VERSION_ID])
                    && $widget['widget_id'] > 0
                ) {
                    // since 2.6.0
                    // use self-managed phrase for widget title
                    /** @var WidgetFramework_Model_Widget $widgetModel */
                    $widgetModel = WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_Widget');
                    return new XenForo_Phrase($widgetModel->getWidgetTitlePhrase($widget['widget_id']));
                } else {
                    // legacy support
                    return $widget['title'];
                }
            }
        } else {
            return $this->getName();
        }
    }

    public function isCacheUsable(array &$cached, array $widget)
    {
        $configuration = $this->getConfiguration();
        if (empty($configuration['useCache'])) {
            return false;
        }

        $cacheSeconds = $configuration['cacheSeconds'];

        if (!empty($widget['options']['cache_seconds'])) {
            $cacheSeconds = intval($widget['options']['cache_seconds']);
        }

        if ($cacheSeconds < 0) {
            return true;
        }

        $seconds = XenForo_Application::$time - $cached['time'];
        if ($seconds > $cacheSeconds) {
            return false;
        }

        return true;
    }

    const FORUMS_OPTION_SPECIAL_CURRENT = 'current_forum';
    const FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN = 'current_forum_and_children';
    const FORUMS_OPTION_SPECIAL_PARENT = 'parent_forum';
    const FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN = 'parent_forum_and_children';
    const EXTRA_CONTAINER_DATA = 'containerData';
    const EXTRA_REQUIRED_EXTERNALS = 'requiredExternals';

    /**
     * @var XenForo_View
     */
    protected static $_pseudoViewObj = null;

    /**
     * @param string $class
     * @return WidgetFramework_WidgetRenderer|WidgetFramework_WidgetRenderer_None
     */
    public static function create($class)
    {
        static $instances = array();

        if (!isset($instances[$class])) {
            $fakeBase = 'WidgetFramework_WidgetRenderer_None';
            $createClass = XenForo_Application::resolveDynamicClass($class, 'widget_renderer', $fakeBase);
            $instances[$class] = $createClass ? new $createClass : new $fakeBase;
        }

        return $instances[$class];
    }

    public static function getNamePrefix()
    {
        return 'options_';
    }

    public static function getViewObject(array $params, XenForo_Template_Abstract $templateObj)
    {
        if (empty(self::$_pseudoViewObj)
            && WidgetFramework_Listener::$viewRenderer !== null
        ) {
            self::$_pseudoViewObj = new XenForo_ViewPublic_Base(
                WidgetFramework_Listener::$viewRenderer,
                XenForo_Application::getFc()->getResponse()
            );
        }

        if (!empty(self::$_pseudoViewObj)) {
            return self::$_pseudoViewObj;
        }

        if (WidgetFramework_Core::debugMode()) {
            // log the exception for admin examination (in our debug mode only)
            XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to get view object for %s',
                $templateObj->getTemplateName())), false, '[bd] Widget Framework');
        }

        return null;
    }

    /**
     * @return WidgetFramework_Model_Cache
     */
    protected function _getCacheModel()
    {
        return WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_Cache');
    }
}
