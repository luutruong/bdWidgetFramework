<?php

class WidgetFramework_WidgetRenderer_ProfilePosts extends WidgetFramework_WidgetRenderer
{
    public function extraPrepare(array $widget, &$html)
    {
        $visitor = XenForo_Visitor::getInstance();
        $html = str_replace('CSRF_TOKEN_PAGE', $visitor->get('csrf_token_page'), $html);
        $html = str_replace('LINK_MEMBER_POST_VISITOR', XenForo_Link::buildPublicLink('members/post', $visitor), $html);

        return parent::extraPrepare($widget, $html);
    }

    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('wf_widget_profile_posts_type_recent');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'Profile Posts',
            'options' => array(
                'limit' => XenForo_Input::UINT,
                'show_update_form' => XenForo_Input::BINARY,
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 3600, // cache for 1 hour
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_profile_posts';
    }

    protected function _getExtraDataLink(array $widget)
    {
        if (XenForo_Application::$versionId > 1040000) {
            return XenForo_Link::buildPublicLink('find-new/profile-posts');
        }

        return parent::_getExtraDataLink($widget);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_profile_posts';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['limit'])) {
            $widget['options']['limit'] = 5;
        }

        $profilePosts = $this->_getProfilePosts($widget, $positionCode, $params, $renderTemplateObject);
        $renderTemplateObject->setParam('profilePosts', $profilePosts);

        if (!empty($widget['options']['show_update_form'])) {
            $renderTemplateObject->setParam('canUpdateStatus', XenForo_Visitor::getInstance()->canUpdateStatus());
        } else {
            $renderTemplateObject->setParam('canUpdateStatus', false);
        }

        return $renderTemplateObject->render();
    }

    protected function _getProfilePosts(
        array $widget,
        $positionCode,
        array $params,
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if ($positionCode == 'forum_list'
            && isset($params['profilePosts'])
            && $widget['options']['limit'] == XenForo_Application::getOptions()->get('forumListNewProfilePosts')
        ) {
            return $params['profilePosts'];
        }

        $core = WidgetFramework_Core::getInstance();
        $visitor = XenForo_Visitor::getInstance();

        /** @var WidgetFramework_Model_ProfilePost $wfProfilePostModel */
        $wfProfilePostModel = $core->getModelFromCache('WidgetFramework_Model_ProfilePost');
        /** @var XenForo_Model_ProfilePost $profilePostModel */
        $profilePostModel = $core->getModelFromCache('XenForo_Model_ProfilePost');

        $profilePosts = $wfProfilePostModel->getLatestProfilePosts(array(
            'deleted' => false,
            'moderated' => false
        ), array(
            'limit' => $widget['options']['limit'] * 3,
            'join' => XenForo_Model_ProfilePost::FETCH_USER_POSTER
                | XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
                | XenForo_Model_ProfilePost::FETCH_USER_RECEIVER_PRIVACY,
            'permissionCombinationId' => $visitor->get('permission_combination_id'),
        ));

        foreach ($profilePosts AS $id => &$profilePost) {
            $receivingUser = $profilePostModel->getProfileUserFromProfilePost($profilePost);
            if (!$profilePostModel->canViewProfilePostAndContainer($profilePost, $receivingUser)) {
                unset($profilePosts[$id]);
            }

            $profilePost = $profilePostModel->prepareProfilePost($profilePost, $receivingUser);
            if (!empty($profilePost['isIgnored'])) {
                unset($profilePosts[$id]);
            }
        }
        $profilePosts = array_slice($profilePosts, 0, $widget['options']['limit'], true);

        return $profilePosts;
    }
}
