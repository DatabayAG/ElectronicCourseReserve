<?php
/**
 * Created by PhpStorm.
 * User: nmatuschek
 * Date: 29.05.18
 * Time: 12:32
 */
class ilElectronicCourseReserveRTEHelper
{

static $content_id = 8888;

	/**
	 * param string $post_message
* @param string $source_type
* @param int $source_id
* @param string $target_type
* @param int $target_id
*/
	public static function moveMediaObjects($content, $source_type,  $target_type,  $direction = 0)
	{
		include_once 'Services/MediaObjects/classes/class.ilObjMediaObject.php';
		$mediaObjects = ilRTE::_getMediaObjects($content, $direction);
		$myMediaObjects = ilObjMediaObject::_getMobsOfObject($source_type, self::$content_id);
		foreach($mediaObjects as $mob)
		{
			foreach($myMediaObjects as $myMob)
			{
				if($mob == $myMob)
				{
					// change usage
					ilObjMediaObject::_removeUsage($mob, $source_type,  self::$content_id);
					break;
				}
			}
			ilObjMediaObject::_saveUsage($mob, $target_type,  self::$content_id);
		}
	}

	/**
	 * @param $post_message
	 * @param $target_type
	 * @param $target_id
	 */
	public static function saveMediaObjects($content, $target_type,  $direction = 0)
	{
		include_once 'Services/MediaObjects/classes/class.ilObjMediaObject.php';
		$mediaObjects = ilRTE::_getMediaObjects($content, $direction);

		foreach($mediaObjects as $mob)
		{
			ilObjMediaObject::_saveUsage($mob, $target_type,  self::$content_id);
		}
	}
}