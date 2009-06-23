<?php

if ( ! defined('EXT')) exit('Invalid file request');

/**
 * Shoutout Class
 *
 * Link to other entries by URL title
 *
 * @package   Shoutout
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Shoutout {

	var $name           = 'Shoutout';
	var $version        = '0.0.2';
	var $description    = 'Link to entries using {{url_title}} or {{"Custom Title":url_title}} syntax';
	var $settings_exist = 'n';
	var $docs_url       = 'http://brandon-kelly.com/';

	var $hooks = array(
		'weblog_entries_row',

		'lg_addon_update_register_source',
		'lg_addon_update_register_addon'
	);

	function Shoutout()
	{
		global $SESS;

		if ( ! isset($SESS->cache[get_class($this)]))
		{
			$SESS->cache[get_class($this)] = array(
				'shoutouts' => array()
			);
		}
		$this->cache = &$SESS->cache[get_class($this)];
	}

	/**
	 * Activate Extension
	 */
	function activate_extension()
	{
		global $DB;

		// delete old hooks
		$DB->query('DELETE FROM exp_extensions
		              WHERE class = "'.get_class($this).'"');

		// add new hooks
		$hook_tmpl = array(
			'class'    => get_class($this),
			'settings' => '',
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		);

		foreach($this->hooks as $hook => $data)
		{
			if (is_string($data))
			{
				$hook = $data;
				$data = array();
			}
			$data = array_merge($hook_tmpl, array('hook' => $hook, 'method' => $hook), $data);
			$DB->query($DB->insert_string('exp_extensions', $data));
		}
	}

	/**
	 * Update Extension
	 */
	function update_extension($current='')
	{
		global $DB;

		if ( ! $current OR $current == $this->version)
		{
			return FALSE;
		}

		//if ($current < '1.1.0')
		//{
		//	// hooks have changed, so go through
		//	// the whole activate_extension() process
		//	$this->activate_extension();
		//}
		//else
		//{
			// just update the version #s
			$DB->query('UPDATE exp_extensions
			              SET version = "'.$this->version.'"
			              WHERE class = "'.get_class($this).'"');
		//}
	}

	/**
	 * Disable Extension
	 */
	function disable_extension()
	{
		global $DB;

		$DB->query($DB->update_string('exp_extensions', array('enabled' => 'n'), 'class = "'.get_class($this).'"'));
	}

	/**
	 * Get Last Call
	 *
	 * @param  mixed  $param  Parameter sent by extension hook
	 * @return mixed  Return value of last extension call if any, or $param
	 * @access private
	 */
	function get_last_call(&$param)
	{
		global $EXT;

		if ($EXT->last_call !== FALSE)
		{
			$param = $EXT->last_call;
		}
	}

	/**
	 * Weblog - Entries - Row hook
	 *
	 * Take the entry data, do what you wish
	 *
	 * @param  object  &$Weblog  the Weblog class object
	 * @param  array   $row      current row data
	 * @return array   Modified $row
	 * @see http://expressionengine.com/developers/extension_hooks/weblog_entries_row/
	 */
	function weblog_entries_row(&$Weblog, $row)
	{
		$this->get_last_call($row);

		foreach($row as $field_id => &$field_data)
		{
			if (substr($field_id, 0, 9) == 'field_id_')
			{
				$field_data = preg_replace_callback('/\{\{ ?("([^"]+)":)?([\w-]+)((\/[\w-]+)?(#[\w-]+)?) ?\}\}/', array(&$this, 'replace_shoutout'), $field_data);
			}
		}

		return $row;
	}

	/**
	 * Replace Shoutout
	 */
	function replace_shoutout($matches)
	{
		global $DB, $SESS;

		$title = $matches[2];
		$url_title = $matches[3];

		if ( ! isset($this->cache['shoutouts'][$url_title]))
		{
			$query = $DB->query('SELECT wt.title, w.blog_url FROM exp_weblog_titles wt, exp_weblogs w
			                       WHERE wt.url_title = "'.$url_title.'" AND wt.weblog_id = w.weblog_id LIMIT 1');

			if ($query->num_rows)
			{
				$url = $query->row['blog_url']
				     . (substr($query->row['blog_url'], -1) != '/' ? '/' : '')
				     . $url_title;
				$this->cache['shoutouts'][$url_title] = array($url, $query->row['title']);
			}
			else
			{
				$this->cache['shoutouts'][$url_title] = FALSE;
			}
		}

		if (is_array($this->cache['shoutouts'][$url_title]))
		{
			if ( ! $title) $title = $this->cache['shoutouts'][$url_title][1];
			return '<a href="'.$this->cache['shoutouts'][$url_title][0].$matches[4].'">'.$title.'</a>';
		}

		return $matches[0];
	}

	/**
	 * LG Addon Updater - Register a New Addon Source
	 *
	 * @param  array  $sources  The existing sources
	 * @return array  Modified $sources
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 */
	function lg_addon_update_register_source($sources)
	{
		$this->get_last_call($sources);

		$source = 'http://brandon-kelly.com/downloads/versions.xml';
		if ( ! in_array($source, $sources))
		{
			$sources[] = $source;
		}

		return $sources;
	}

	/**
	 * LG Addon Updater - Register a New Addon ID
	 *
	 * @param  array  $addons  The existing sources
	 * @return array  Modified $addons
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 */
	function lg_addon_update_register_addon($addons)
	{
		$this->get_last_call($addons);

		$addons[get_class($this)] = $this->version;

		return $addons;
	}
}