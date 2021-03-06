<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  Table
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Core content table
 *
 * @package     Joomla.Libraries
 * @subpackage  Table
 * @since       3.1
 */
class JTableCorecontent extends JTable
{
	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  A database connector object
	 *
	 * @since   3.1
	 */
	public function __construct($db)
	{
		parent::__construct('#__core_content', 'core_content_id', $db);
	}

	/**
	 * Overloaded bind function
	 *
	 * @param   array  $array   Named array
	 * @param   mixed  $ignore  An optional array or space separated list of properties
	 *                          to ignore while binding.
	 *
	 * @return  mixed  Null if operation was satisfactory, otherwise returns an error string
	 *
	 * @see     JTable::bind
	 * @since   3.1
	 */
	public function bind($array, $ignore = '')
	{

		if (isset($array['core_params']) && is_array($array['core_params']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['core_params']);
			$array['core_params'] = (string) $registry;
		}

		if (isset($array['core_metadata']) && is_array($array['core_metadata']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['core_metadata']);
			$array['core_metadata'] = (string) $registry;
		}

		if (isset($array['core_images']) && is_array($array['core_images']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['core_images']);
			$array['core_images'] = (string) $registry;
		}

		if (isset($array['core_urls']) && is_array($array['core_urls']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['core_urls']);
			$array['core_urls'] = (string) $registry;
		}

		if (isset($array['core_body']) && is_array($array['core_body']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['core_body']);
			$array['core_body'] = (string) $registry;
		}

		return parent::bind($array, $ignore);
	}

	/**
	 * Overloaded check function
	 *
	 * @return  boolean  True on success, false on failure
	 *
	 * @see     JTable::check
	 * @since   3.1
	 */
	public function check()
	{
		if (trim($this->core_title) == '')
		{
			$this->setError(JText::_('LIB_CMS_WARNING_PROVIDE_VALID_NAME'));
			return false;
		}

		if (trim($this->core_alias) == '')
		{
			$this->core_alias = $this->core_title;
		}

		$this->core_alias = JApplication::stringURLSafe($this->core_alias);

		if (trim(str_replace('-', '', $this->core_alias)) == '')
		{
			$this->core_alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}

		// Check the publish down date is not earlier than publish up.
		if ($this->core_publish_down > $this->_db->getNullDate() && $this->core_publish_down < $this->core_publish_up)
		{
			// Swap the dates.
			$temp = $this->core_publish_up;
			$this->core_publish_up = $this->core_publish_down;
			$this->core_publish_down = $temp;
		}

		// Clean up keywords -- eliminate extra spaces between phrases
		// and cr (\r) and lf (\n) characters from string
		if (!empty($this->core_metakey))
		{
			// Only process if not empty

			// Array of characters to remove
			$bad_characters = array("\n", "\r", "\"", "<", ">");

			// Remove bad characters
			$after_clean = JString::str_ireplace($bad_characters, "", $this->metakey);

			// Create array using commas as delimiter
			$keys = explode(',', $after_clean);

			$clean_keys = array();

			foreach ($keys as $key)
			{
				if (trim($key))
				{
					// Ignore blank keywords
					$clean_keys[] = trim($key);
				}
			}
			// Put array back together delimited by ", "
			$this->core_metakey = implode(", ", $clean_keys);
		}

		return true;
	}

	/**
	 * Overrides JTable::store to set modified data and user id.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function store($updateNulls = false)
	{
		$date = JFactory::getDate();
		$user = JFactory::getUser();

		if ($this->core_content_id)
		{
			// Existing item
			$this->core_modified_time = $date->toSql();
			$this->core_modified_user_id = $user->get('id');
		}
		else
		{
			// New content item. A content item core_created_time and core_created_user_id field can be set by the user,
			// so we don't touch either of these if they are set.
			if (!(int) $this->core_created_time)
			{
				$this->core_created_time = $date->toSql();
			}

			if (empty($this->core_created_user_id))
			{
				$this->core_created_user_id = $user->get('id');
			}
		}
		// Verify that the alias is unique
		$table = JTable::getInstance('Corecontent', 'JTable');
		if (
			$table->load(array('core_alias' => $this->core_alias, 'core_catid' => $this->core_catid))
			&& ($table->core_content_id != $this->core_content_id || $this->core_content_id == 0)
		)
		{
			$this->setError(JText::_('JLIB_DATABASE_ERROR_ARTICLE_UNIQUE_ALIAS'));
			return false;
		}

		return parent::store($updateNulls);
	}

	/**
	 * Method to set the publishing state for a row or list of rows in the database
	 * table. The method respects checked out rows by other users and will attempt
	 * to checkin rows that it can after adjustments are made.
	 *
	 * @param   mixed    $pks     An optional array of primary key values to update.  If not set the instance property value is used.
	 * @param   integer  $state   The publishing state. eg. [0 = unpublished, 1 = published]
	 * @param   integer  $userId  The user id of the user performing the operation.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function publish($pks = null, $state = 1, $userId = 0)
	{
		$k = $this->_tbl_key;

		// Sanitize input.
		JArrayHelper::toInteger($pks);
		$userId = (int) $userId;
		$state = (int) $state;

		// If there are no primary keys set check to see if the instance key is set.
		if (empty($pks))
		{
			if ($this->$k)
			{
				$pks = array($this->$k);
			}
			// Nothing to set publishing state on, return false.
			else
			{
				$this->setError(JText::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
				return false;
			}
		}

		$pksImploded = implode(',', $pks);

		// Get the JDatabaseQuery object
		$query = $this->_db->getQuery(true);

		// Update the publishing state for rows with the given primary keys.
		$query->update($this->_db->quoteName($this->_tbl))
			->set($this->_db->quoteName('core_state') . ' = ' . (int) $state)
			->where($this->_db->quoteName($k) . 'IN (' . $pksImploded . ')');

		// Determine if there is checkin support for the table.
		$checkin = false;
		if (property_exists($this, 'core_checked_out_user_id') && property_exists($this, 'core_checked_out_time'))
		{
			$checkin = true;
			$query->where(' (' . $this->_db->quoteName('core_checked_out_user_id') . ' = 0 OR ' . $this->_db->quoteName('core_checked_out_user_id') . ' = ' . (int) $userId . ')');
		}
		$this->_db->setQuery($query);

		try
		{
			$this->_db->execute();
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());
			return false;
		}

		// If checkin is supported and all rows were adjusted, check them in.
		if ($checkin && (count($pks) == $this->_db->getAffectedRows()))
		{
			// Checkin the rows.
			foreach ($pks as $pk)
			{
				$this->checkin($pk);
			}
		}

		// If the JTable instance value is in the list of primary keys that were set, set the instance.
		if (in_array($this->$k, $pks))
		{
			$this->core_state = $state;
		}

		$this->setError('');

		return true;
	}
}
