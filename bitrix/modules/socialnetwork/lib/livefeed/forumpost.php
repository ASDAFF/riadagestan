<?php
namespace Bitrix\Socialnetwork\Livefeed;

use Bitrix\Forum\ForumTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Forum\MessageTable;
use Bitrix\Socialnetwork\LogCommentTable;
use Bitrix\Socialnetwork\LogTable;

Loc::loadMessages(__FILE__);

final class ForumPost extends Provider
{
	const PROVIDER_ID = 'FORUM_POST';
	const CONTENT_TYPE_ID = 'FORUM_POST';

	public static function getId()
	{
		return static::PROVIDER_ID;
	}

	public function getEventId()
	{
		return array(
			'forum',
			'tasks_comment',
			'calendar_comment',
			'timeman_entry_comment',
			'report_comment',
			'photo_comment',
			'wiki_comment',
			'lists_new_element_comment'
		);
	}

	public function getType()
	{
		return Provider::TYPE_COMMENT;
	}

	public function initSourceFields()
	{
		$messageId = $this->entityId;

		if (
			$messageId > 0
			&& Loader::includeModule('forum')
		)
		{
			$res = MessageTable::getList(array(
				'filter' => array(
					'=ID' => $messageId
				),
				'select' => array('ID', 'POST_MESSAGE')
			));
			if ($message = $res->fetch())
			{
				$logId = false;

				$res = LogCommentTable::getList(array(
					'filter' => array(
						'SOURCE_ID' => $messageId,
						'@EVENT_ID' => $this->getEventId(),
					),
					'select' => array('LOG_ID')
				));
				if ($logComentFields = $res->fetch())
				{
					$logId = intval($logComentFields['LOG_ID']);
				}

				if ($logId)
				{
					$res = \CSocNetLog::getList(
						array(),
						array(
							'=ID' => $logId
						),
						false,
						false,
						array('ID', 'EVENT_ID'),
						array(
							"CHECK_RIGHTS" => "Y",
							"USE_FOLLOW" => "N",
							"USE_SUBSCRIBE" => "N"
						)
					);
					if ($logFields = $res->fetch())
					{
						$this->setLogId($logFields['ID']);
						$this->setSourceFields(array_merge($message, array('LOG_EVENT_ID' => $logFields['EVENT_ID'])));
						$this->setSourceDescription($message['POST_MESSAGE']);

						$title = htmlspecialcharsback($message['POST_MESSAGE']);
						$title = preg_replace(
							"/\[USER\s*=\s*([^\]]*)\](.+?)\[\/USER\]/is".BX_UTF_PCRE_MODIFIER,
							"\\2",
							$title
						);
						$CBXSanitizer = new \CBXSanitizer;
						$CBXSanitizer->delAllTags();
						$title = preg_replace(array("/\n+/is".BX_UTF_PCRE_MODIFIER, "/\s+/is".BX_UTF_PCRE_MODIFIER), " ", $CBXSanitizer->sanitizeHtml($title));
						$this->setSourceTitle(truncateText($title, 100));
						$this->setSourceAttachedDiskObjects($this->getAttachedDiskObjects($messageId));
						$this->setSourceDiskObjects($this->getDiskObjects($messageId, $this->cloneDiskObjects));
					}
				}
			}
		}
	}

	protected function getAttachedDiskObjects($clone = false)
	{
		global $USER_FIELD_MANAGER;
		static $cache = array();

		$messageId = $this->entityId;

		$result = array();
		$cacheKey = $messageId.$clone;

		if (isset($cache[$cacheKey]))
		{
			$result = $cache[$cacheKey];
		}
		else
		{
			$messageUF = $USER_FIELD_MANAGER->getUserFields("FORUM_MESSAGE", $messageId, LANGUAGE_ID);
			if (
				!empty($messageUF['UF_FORUM_MESSAGE_DOC'])
				&& !empty($messageUF['UF_FORUM_MESSAGE_DOC']['VALUE'])
				&& is_array($messageUF['UF_FORUM_MESSAGE_DOC']['VALUE'])
			)
			{
				if ($clone)
				{
					$this->attachedDiskObjectsCloned = self::cloneUfValues($messageUF['UF_FORUM_MESSAGE_DOC']['VALUE']);
					$result = $cache[$cacheKey] = array_values($this->attachedDiskObjectsCloned);
				}
				else
				{
					$result = $cache[$cacheKey] = $messageUF['UF_FORUM_MESSAGE_DOC']['VALUE'];
				}
			}
		}

		return $result;
	}

	public static function canRead($params)
	{
		return true;
	}

	protected function getPermissions(array $post)
	{
		$result = self::PERMISSION_READ;

		return $result;
	}

	public function getLiveFeedUrl()
	{
		static $urlCache = array();
		$result = '';

		$entityUrl = false;

		$logId = $this->getLogId();

		if ($logId)
		{
			if (isset($urlCache[$logId]))
			{
				$entityUrl = $urlCache[$logId];
			}
			else
			{
				$res = LogTable::getList(array(
					'filter' => array(
						'ID' => $logId
					),
					'select' => array('EVENT_ID', 'SOURCE_ID', 'RATING_ENTITY_ID', 'PARAMS')
				));
				if ($logEntryFields = $res->fetch())
				{
					$provider = false;

					$providerTasksTask = new TasksTask();
					if (in_array($logEntryFields['EVENT_ID'], $providerTasksTask->getEventId()))
					{
						$provider = $providerTasksTask;
						$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
						$provider->initSourceFields();
						$entityUrl = $provider->getLiveFeedUrl().'?commentId='.$this->getEntityId().'#com'.$this->getEntityId();
					}

					if (!$provider)
					{
						$providerCalendarEvent = new CalendarEvent();
						if (in_array($logEntryFields['EVENT_ID'], $providerCalendarEvent->getEventId()))
						{
							$provider = $providerCalendarEvent;
							$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
							$provider->initSourceFields();
							$entityUrl = $provider->getLiveFeedUrl().'?commentId='.$this->getEntityId().'#com'.$this->getEntityId();
						}
					}

					if (!$provider)
					{
						$providerTimemanEntry = new TimemanEntry();
						if (in_array($logEntryFields['EVENT_ID'], $providerTimemanEntry->getEventId()))
						{
							$provider = $providerTimemanEntry;
							$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
							$provider->initSourceFields();
							$entityUrl = $provider->getLiveFeedUrl();
						}
					}

					if (!$provider)
					{
						$providerTimemanReport = new TimemanReport();
						if (in_array($logEntryFields['EVENT_ID'], $providerTimemanReport->getEventId()))
						{
							$provider = $providerTimemanReport;
							$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
							$provider->initSourceFields();
							$entityUrl = $provider->getLiveFeedUrl();
						}
					}

					if (!$provider)
					{
						$providerPhotogalleryPhoto = new PhotogalleryPhoto();
						if (in_array($logEntryFields['EVENT_ID'], $providerPhotogalleryPhoto->getEventId()))
						{
							$provider = $providerPhotogalleryPhoto;
							$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
							$provider->initSourceFields();
							$entityUrl = $provider->getLiveFeedUrl();
						}
					}

					if (!$provider)
					{
						$providerWiki = new Wiki();
						if (in_array($logEntryFields['EVENT_ID'], $providerWiki->getEventId()))
						{
							$provider = $providerWiki;
							$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
							$provider->initSourceFields();
							$entityUrl = $provider->getLiveFeedUrl();
						}
					}

					if (!$provider)
					{
						$providerListsItem = new ListsItem();
						if (in_array($logEntryFields['EVENT_ID'], $providerListsItem->getEventId()))
						{
							$provider = $providerListsItem;
							$provider->setEntityId(intval($logEntryFields['SOURCE_ID']));
							$provider->initSourceFields();
							$entityUrl = $provider->getLiveFeedUrl().'?commentId='.$this->getEntityId().'#com'.$this->getEntityId();
						}
					}

					if (!$provider)
					{
						$providerForumTopic = new ForumTopic();
						if (in_array($logEntryFields['EVENT_ID'], $providerForumTopic->getEventId()))
						{
							if (
								!empty($logEntryFields["PARAMS"])
								&& unserialize($logEntryFields["PARAMS"])
							)
							{
								$paramsList = unserialize($logEntryFields["PARAMS"]);
								if (!empty($paramsList["PATH_TO_MESSAGE"]))
								{
									$entityUrl = \CComponentEngine::makePathFromTemplate($paramsList["PATH_TO_MESSAGE"], array("MID" => $this->getEntityId()));
								}
							}
						}
					}
				}
			}
		}

		if (!empty($entityUrl))
		{
			$result = $entityUrl;
		}

		return $result;
	}

	public function getSuffix()
	{
		$logEventId = $this->getLogEventId();

		if (!empty($logEventId))
		{
			$providerTasksTask = new TasksTask();
			if (in_array($logEventId, $providerTasksTask->getEventId()))
			{
				return 'TASK';
			}

			$providerCalendarEvent = new CalendarEvent();
			if (in_array($logEventId, $providerCalendarEvent->getEventId()))
			{
				return 'CALENDAR';
			}

			$providerForumTopic = new ForumTopic();
			if (in_array($logEventId, $providerForumTopic->getEventId()))
			{
				return 'FORUM_TOPIC';
			}

			$providerTimemanEntry = new TimemanEntry();
			if (in_array($logEventId, $providerTimemanEntry->getEventId()))
			{
				return 'TIMEMAN_ENTRY';
			}

			$providerTimemanReport = new TimemanReport();
			if (in_array($logEventId, $providerTimemanReport->getEventId()))
			{
				return 'TIMEMAN_REPORT';
			}

			$providerPhotogalleryPhoto = new PhotogalleryPhoto();
			if (in_array($logEventId, $providerPhotogalleryPhoto->getEventId()))
			{
				return 'PHOTO_PHOTO';
			}

			$providerWiki = new Wiki();
			if (in_array($logEventId, $providerWiki->getEventId()))
			{
				return 'WIKI';
			}

			$providerListsItem = new ListsItem();
			if (in_array($logEventId, $providerListsItem->getEventId()))
			{
				return 'LISTS_NEW_ELEMENT';
			}
		}
		return '';
	}

	public function add($params = array())
	{
		global $USER, $DB;

		static $parser = null;

		$siteId = (
			isset($params['SITE_ID'])
			&& strlen($params['SITE_ID']) > 0
				? $params['SITE_ID']
				: SITE_ID
		);

		$authorId = (
			isset($params['AUTHOR_ID'])
			&& intval($params['AUTHOR_ID']) > 0
				? intval($params['AUTHOR_ID'])
				: $USER->getId()
		);

		$message = (
			isset($params['MESSAGE'])
			&& strlen($params['MESSAGE']) > 0
			? $params['MESSAGE']
			: ''
		);

		if (
			strlen($message) <= 0
			|| !Loader::includeModule('forum')
		)
		{
			return false;
		}

		$forumId = self::getForumId(array(
			'SITE_ID' => $siteId
		));

		if (!$forumId)
		{
			return false;
		}

		$logId = $this->getLogId();

		if (!$logId)
		{
			return false;
		}

		$this->setLogId($logId);
		$feedParams = $this->getFeedParams();

		if (empty($feedParams))
		{
			return false;
		}

		$feed = new \Bitrix\Forum\Comments\Feed(
			$forumId,
			$feedParams,
			$authorId
		);

		$forumComment = $feed->add(array(
			'POST_MESSAGE' => $message,
			'AUTHOR_ID' => $authorId,
			'USE_SMILES' => 'Y',
			'AUX' => (isset($params['AUX']) && $params['AUX'] == 'Y' ? $params['AUX'] : 'N')
		));

		if (!$forumComment)
		{
			return false;
		}

		$sonetCommentId = false;

		if ($params['AUX'] == 'Y')
		{
			if ($parser === null)
			{
				$parser = new \CTextParser();
			}

			$sonetCommentFields = array(
				"ENTITY_TYPE" => $this->getLogEntityType(),
				"ENTITY_ID" => $this->getLogEntityId(),
				"EVENT_ID" => $this->getCommentEventId(),
				"MESSAGE" => $message,
				"TEXT_MESSAGE" => $parser->convert4mail($message),
				"MODULE_ID" => $this->getModuleId(),
				"SOURCE_ID" => $forumComment['ID'],
				"LOG_ID" => $logId,
				"RATING_TYPE_ID" => "FORUM_POST",
				"RATING_ENTITY_ID" => $forumComment['ID'],
				"USER_ID" => $authorId,
				"=LOG_DATE" => $DB->currentTimeFunction(),
			);

			if (!empty($params['SHARE_DEST']))
			{
				$sonetCommentFields['SHARE_DEST'] = $params['SHARE_DEST'];
			}

			$sonetCommentId = \CSocNetLogComments::add($sonetCommentFields, false, false);
		}
		else // comment is added on event
		{
			$res = LogCommentTable::getList(array(
				'filter' => array(
					'EVENT_ID' => $this->getCommentEventId(),
					'SOURCE_ID' => $forumComment['ID']
				),
				'select' => array('ID')
			));
			if ($sonetCommentFields = $res->fetch())
			{
				$sonetCommentId = $sonetCommentFields['ID'];
			}
		}

		return $sonetCommentId;
	}

	private static function getForumId($params = array())
	{
		$siteId = (
			isset($params['SITE_ID'])
			&& strlen($params['SITE_ID']) > 0
				? $params['SITE_ID']
				: SITE_ID
		);

		$result = Option::get('tasks', 'task_forum_id', 0, $siteId);

		if (intval($result) <= 0)
		{
			$res = ForumTable::getList(array(
				'filter' => array(
					'=XML_ID' => 'intranet_tasks'
				),
				'select' => array('ID')
			));
			if ($forumFields = $res->fetch())
			{
				$result = intval($forumFields['ID']);
			}
		}

		return $result;
	}

	private function getCommentEventId()
	{

		$result = false;

		$logEventId = $this->getLogEventId();
		if (!$logEventId)
		{
			return $result;
		}

		switch($logEventId)
		{
			case 'tasks':
				$result = 'tasks_comment';
				break;
			case 'calendar':
				$result = 'calendar_comment';
				break;
			case 'forum':
				$result = 'forum';
				break;
			case 'timeman_entry':
				$result = 'timeman_entry_comment';
				break;
			case 'report':
				$result = 'report_comment';
				break;
			case 'photo_photo':
				$result = 'photo_comment';
				break;
			case 'wiki':
				$result = 'wiki_comment';
				break;
			case 'lists_new_element':
				$result = 'lists_new_element_comment';
				break;
			default:
				$result = false;
		}

		return $result;
	}

	private function getModuleId()
	{
		$result = false;

		$logEventId = $this->getLogEventId();
		if (!$logEventId)
		{
			return $result;
		}

		switch($logEventId)
		{
			case 'tasks':
				$result = 'tasks';
				break;
			case 'calendar':
				$result = 'calendar';
				break;
			case 'forum':
				$result = 'forum';
				break;
			case 'timeman_entry':
				$result = 'timeman';
				break;
			case 'photo_photo':
				$result = 'photogallery';
				break;
			case 'wiki':
				$result = 'wiki';
				break;
			default:
				$result = false;
		}

		return $result;
	}

	private function getFeedParams()
	{
		$result = array();

		$logId = $this->getLogId();

		if (!$logId)
		{
			return $result;
		}

		$res = LogTable::getList(array(
			'filter' => array(
				'ID' => $logId
			),
			'select' => array('EVENT_ID', 'SOURCE_ID')
		));
		if (
			($logFields = $res->fetch())
			&& (!empty($logFields['EVENT_ID']))
			&& (intval($logFields['SOURCE_ID']) > 0)
		)
		{
			$this->setLogEventId($logFields['EVENT_ID']);

			$providerTasksTask = new TasksTask();
			if (in_array($logFields['EVENT_ID'], $providerTasksTask->getEventId()))
			{
				$result = array(
					"type" => "TK",
					"id" => intval($logFields['SOURCE_ID']),
					"xml_id" => "TASK_".intval($logFields['SOURCE_ID'])
				);
			}

			if (empty($result))
			{
				$providerCalendarEvent = new CalendarEvent();
				if (in_array($logFields['EVENT_ID'], $providerCalendarEvent->getEventId()))
				{
					$result = array(
						"type" => "EV",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "EVENT_".intval($logFields['SOURCE_ID'])
					);
				}
			}

			if (empty($result))
			{
				$providerForumTopic = new ForumTopic();
				if (in_array($logFields['EVENT_ID'], $providerForumTopic->getEventId()))
				{
					$result = array(
						"type" => "DEFAULT",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "TOPIC_".intval($logFields['SOURCE_ID'])
					);
				}
			}

			if (empty($result))
			{
				$providerTimemanEntry = new TimemanEntry();
				if (in_array($logFields['EVENT_ID'], $providerTimemanEntry->getEventId()))
				{
					$result = array(
						"type" => "TE",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "TIMEMAN_ENTRY_".intval($logFields['SOURCE_ID'])
					);
				}
			}

			if (empty($result))
			{
				$providerTimemanReport = new TimemanReport();
				if (in_array($logFields['EVENT_ID'], $providerTimemanReport->getEventId()))
				{
					$result = array(
						"type" => "TR",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "TIMEMAN_REPORT_".intval($logFields['SOURCE_ID'])
					);
				}
			}

			if (empty($result))
			{
				$providerPhotogalleryPhoto = new PhotogalleryPhoto();
				if (in_array($logFields['EVENT_ID'], $providerPhotogalleryPhoto->getEventId()))
				{
					$result = array(
						"type" => "PH",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "PHOTO_".intval($logFields['SOURCE_ID'])
					);
				}
			}

			if (empty($result))
			{
				$providerWiki = new Wiki();
				if (in_array($logFields['EVENT_ID'], $providerWiki->getEventId()))
				{
					$result = array(
						"type" => "IBLOCK",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "IBLOCK_".intval($logFields['SOURCE_ID'])
					);
				}
			}

			if (empty($result))
			{
				$providerListsItem = new ListsItem();
				if (in_array($logFields['EVENT_ID'], $providerListsItem->getEventId()))
				{
					$result = array(
						"type" => "WF",
						"id" => intval($logFields['SOURCE_ID']),
						"xml_id" => "WF_".intval($logFields['SOURCE_ID'])
					);
				}
			}
		}

		return $result;
	}

}