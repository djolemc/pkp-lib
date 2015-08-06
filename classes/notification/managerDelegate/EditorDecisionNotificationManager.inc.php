<?php

/**
 * @file classes/notification/managerDelegate/EditorDecisionNotificationManager.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Editor decision notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class EditorDecisionNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function EditorDecisionNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationMessage()
	 */
	function getNotificationMessage($request, $notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW:
				return __('notification.type.editorDecisionInternalReview');
			case NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
				return __('notification.type.editorDecisionAccept');
			case NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
				return __('notification.type.editorDecisionExternalReview');
			case NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
				return __('notification.type.editorDecisionPendingRevisions');
			case NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
				return __('notification.type.editorDecisionResubmit');
			case NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
				return __('notification.type.editorDecisionDecline');
			case NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
				return __('notification.type.editorDecisionSendToProduction');
			default:
				return null;
		}
	}

	/**
	 * @copydoc NotificationManagerDelegate::getStyleClass()
	 */
	function getStyleClass($notification) {
		return NOTIFICATION_STYLE_CLASS_INFORMATION;
	}

	/**
	 * @copydoc NotificationManagerDelegate::getNotificationTitle()
	 */
	function getNotificationTitle($notification) {
		return __('notification.type.editorDecisionTitle');
	}

	/**
	 * @copydoc NotificationManagerDelegate::updateNotification()
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		$context = $request->getContext();

		// Remove any existing editor decision notifications.
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationFactory = $notificationDao->getByAssoc(
			ASSOC_TYPE_SUBMISSION,
			$assocId,
			null,
			null,
			$context->getId()
		);

		// Delete old notifications.
		$editorDecisionNotificationTypes = $this->_getAllEditorDecisionNotificationTypes();
		while ($notification = $notificationFactory->next()) {
			// If a list of user IDs was specified, make sure we're respecting it.
			if ($userIds !== null && !in_array($notification->getUserId(), $userIds)) continue;

			// Check that the notification type is in the specified list.
			if (!in_array($notification->getType(), $editorDecisionNotificationTypes)) continue;

			$notificationDao->deleteObject($notification);
		}

		// (Re)create notifications.
		foreach ((array) $userIds as $userId) $this->createNotification(
			$request,
			$userId,
			$this->getNotificationType(),
			$context->getId(),
			ASSOC_TYPE_SUBMISSION,
			$assocId,
			$this->_getNotificationTaskLevel($this->getNotificationType())
		);
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW:
			case NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT:
			case NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW:
			case NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
			case NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT:
			case NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE:
			case NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION:
				$submissionDao = Application::getSubmissionDAO();
				$submission = $submissionDao->getById($notification->getAssocId());
				import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');
				list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission);
				$router = $request->getRouter();
				$dispatcher = $router->getDispatcher();
				$contextDao = Application::getContextDAO();
				$context = $contextDao->getById($submission->getContextId());
				// this will probably be authorDashboard/submission, but the possibility exists that an editor is
				// revising a submission without being an author in the stage assignments.
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), $page, $operation, $submission->getId());
			default:
				return '';
		}
	}

	//
	// Private helper methods
	//
	/**
	 * Get all notification types corresponding to editor decisions.
	 * @return array
	 */
	function _getAllEditorDecisionNotificationTypes() {
		return array(
			NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW,
			NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT,
			NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW,
			NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS,
			NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT,
			NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE,
			NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION
		);
	}

	/**
	 * Get the notification level for the type of notification being created.
	 * @param int $type
	 * @return int
	 */
	function _getNotificationTaskLevel($type) {
		switch ($type) {
			case NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS:
				return NOTIFICATION_LEVEL_TASK;
			default:
				return NOTIFICATION_LEVEL_NORMAL;
		}
	}
}

?>