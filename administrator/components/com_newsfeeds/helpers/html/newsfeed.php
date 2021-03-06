<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_newsfeeds
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::register('NewsfeedsHelper', JPATH_ADMINISTRATOR . '/components/com_newsfeeds/helpers/newsfeeds.php');

/**
 * Utility class for creating HTML Grids
 *
 * @static
 * @package     Joomla.Administrator
 * @subpackage  com_newsfeeds
 * @since       1.5
 */
class JHtmlNewsfeed
{
	/**
	 * @param   int $value	The state value
	 * @param   int $i
	 */
	public static function state($value = 0, $i)
	{
		// Array of image, task, title, action
		$states	= array(
			1	=> array('tick.png',		'newsfeeds.unpublish',	'JPUBLISHED',			'COM_NEWSFEEDS_UNPUBLISH_ITEM'),
			0	=> array('publish_x.png',	'newsfeeds.publish',		'JUNPUBLISHED',		'COM_NEWSFEEDS_PUBLISH_ITEM')
		);
		$state	= JArrayHelper::getValue($states, (int) $value, $states[0]);
		$html	= '<a href="#" onclick="return listItemTask(\'cb'.$i.'\',\''.$state[1].'\')" title="'.JText::_($state[3]).'">'
				. JHtml::_('image', 'admin/'.$state[0], JText::_($state[2]), null, true).'</a>';

		return $html;
	}

	/**
	 * Display an HTML select list of state filters
	 *
	 * @param   int $selected	The selected value of the list
	 * @return  string  	The HTML code for the select tag
	 * @since   1.6
	 */
	public static function filterstate($selected)
	{
		// Build the active state filter options.
		$options	= array();
		$options[]	= JHtml::_('select.option', '*', JText::_('JOPTION_ANY'));
		$options[]	= JHtml::_('select.option', '1', JText::_('JPUBLISHED'));
		$options[]	= JHtml::_('select.option', '0', JText::_('JUNPUBLISHED'));

		return JHtml::_('select.genericlist', $options, 'filter_published',
			array(
				'list.attr' => 'class="inputbox" onchange="this.form.submit();"',
				'list.select' => $selected
			)
		);
	}

	/**
	 * Get the associated language flags
	 *
	 * @param   int  $newsfeedid  The item id to search associations
	 *
	 * @return  string  The language HTML
	 */
	public static function association($newsfeedid)
	{
		// Defaults
		$html = '';

		// Get the associations
		if ($associations = JLanguageAssociations::getAssociations('com_newsfeeds', '#__newsfeeds', 'com_newsfeeds.item', $newsfeedid))
		{
			foreach ($associations as $tag => $associated)
			{
				$associations[$tag] = (int) $associated->id;
			}

			// Get the associated newsfeed items
			$db = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select('c.*')
				->from('#__newsfeeds as c')
				->select('cat.title as category_title')
				->join('LEFT', '#__categories as cat ON cat.id=c.catid')
				->where('c.id IN (' . implode(',', array_values($associations)) . ')')
				->join('LEFT', '#__languages as l ON c.language=l.lang_code')
				->select('l.image')
				->select('l.title as language_title');
			$db->setQuery($query);

			try
			{
				$items = $db->loadObjectList('id');
			}
			catch (runtimeException $e)
			{
				throw new Exception($e->getMessage(), 500);

				return false;
			}

			$tags = array();

			// Construct html
			foreach ($associations as $tag => $associated)
			{
				if ($associated != $newsfeedid)
				{
					$tags[] = JText::sprintf('COM_NEWSFEEDS_TIP_ASSOCIATED_LANGUAGE',
						JHtml::_('image', 'mod_languages/' . $items[$associated]->image . '.gif',
							$items[$associated]->language_title,
							array('title' => $items[$associated]->language_title),
							true
						),
						$items[$associated]->name, $items[$associated]->category_title
					);
				}
			}
			$html = JHtml::_('tooltip', implode('<br />', $tags), JText::_('COM_NEWSFEEDS_TIP_ASSOCIATION'), 'admin/icon-16-links.png');
		}

		return $html;
	}
}
