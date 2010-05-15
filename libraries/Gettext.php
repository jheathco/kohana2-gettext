<?php

class Gettext_Core
{
	public static $config;

	public static $instance;

	/**
	 * Returns the Gettext singleton instance
	 *
	 * @return  Gettext
	 */
	public static function instance()
	{
		if ( ! isset(Gettext::$instance))
		{
			// Load a new instance
			Gettext::$instance = new Gettext();
		}

		return Gettext::$instance;
	}


	/**
	 * Sets current locale, must be used if putenv is not called
	 *
	 * @param  string  locale to switch to
	 * @return string  new value
	 */
	public static function set_locale($locale)
	{
		// Set environment variable
		putenv('LC_ALL='.$locale);

		// Set locale
		return setlocale(LC_ALL, $locale);
	}

	/**
	 * Generates PO files from project defined in gettext config
	 *
	 * @return void
	 */
	public static function generate_po_files()
	{
		Gettext::_create_folder_structure();

		// Scan project for language keys
		$items = Gettext::scan();

		foreach (Gettext::$config['locales'] as $locale)
		{
			$path = APPPATH.Gettext::$config['directory'].'/'.$locale.'/LC_MESSAGES/'.Gettext::$config['domain'].'.po';

			if (is_file($path))
			{
				// Parse existing PO file
				$po = Gettext::parse_po_file($path);
			}
			else
			{
				// Empty array for no PO file
				$po = array();
			}

			foreach (array_keys($po) as $key)
			{
				if ( ! isset($items[$key]))
				{
					// Loop thru PO file keys removing any that don't exist in the new scan
					unset($po[$key]);
				}
			}

			foreach ($items as $key => $item)
			{
				if (isset($po[$key]))
				{
					// Use existing PO message string definitions
					$items[$key]['vals'] = $po[$key]['vals'];
				}
				else
				{
					// Use a NULL string for definitions that have not yet been defined
					$items[$key]['vals'] = array('');
				}
			}

			$data = '';

			foreach ($items as $key => $item)
			{
				foreach ($item['refs'] as $ref)
				{
					// File/line number references
					$data .= '#: '.$ref."\n";
				}

				// msgid
				$data .= 'msgid "'.$key.'"'."\n";

				foreach ($item['vals'] as $val)
				{
					// msgstr
					$data .= 'msgstr "'.$val.'"'."\n";

					// Just use first value for now.. support multiple later (for plural, etc.)
					break;
				}

				$data .= "\n";
			}

			// Write to the PO file
			file_put_contents($path, $data);
		}
	}

	/**
	 * Creates necessary folder structure for PO/MO files
	 *
	 * @return void
	 */
	protected static function _create_folder_structure()
	{
		$base = Gettext::$config['directory'];

		foreach (Gettext::$config['locales'] as $locale)
		{
			$path = APPPATH.Gettext::$config['directory'].'/'.$locale.'/LC_MESSAGES';

			if ( ! is_dir($path))
			{
				// Create locale path
				mkdir($path, 0777, TRUE);
			}
		}
	}

	/**
	 * Scans project for language keys
	 *
	 * @return array  language key mapping
	 */
	public static function scan()
	{
		$files = array();

		foreach (Gettext::$config['includes'] as $include)
		{
			// Scan files in for the given include
			$files = array_merge($files, Kohana::list_files($include, TRUE));
		}

		// Stores key mappings
		$items = array();

		// Stores the last line the given key was encounted on for the given file
		$key_last_line = array();

		foreach ($files as $file)
		{
			if (is_file($file))
			{
				$data = file_get_contents($file);

				$lines = explode("\n", $data);

				foreach (array('\'', '"') as $char)
				{
					// Find all occurrences of __('...') or __("...")

					if (preg_match_all('/__\('.$char.'(.*?[^\\\\])'.$char.'\)/s', $data, $match))
					{
						foreach ($match[1] as $text)
						{
							// Line to start looking for this text when we're finding the line number
							$i = isset($key_last_line[$file][$text]) ? $key_last_line[$file][$text] + 1 : 0;

							for (; $i < count($lines); $i++)
							{
								if (strpos($lines[$i], '__('.$char.$text.$char.')') !== FALSE)
								{
									// Determine line number this occurs in and set this as the last line it was found
									$key_last_line[$file][$text] = $i;
									break;
								}
							}

							if ($i != count($lines))
							{
								$text = str_replace('\\\'', '\'', $text);

								if ($char == '\'')
								{
									// Escape " instances in ' delimited strings
									$text = str_replace('"', '\"', $text);
								}

								// Line number is valid, store the references
								$items[$text]['refs'][] = $file.':'.($i+1);
							}
						}
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Parses the given PO file into a kep mapping array
	 *
	 * @return array  key mappings
	 */
	public static function parse_po_file($file)
	{
		$data = file_get_contents($file);

		$lines = explode("\n", $data);

		$msgs = array();

		$in     = NULL;
		$msgid  = NULL;
		$msgstr = NULL;

		$refs = array();

		foreach ($lines as $line)
		{
			if (preg_match('/^#: (.*)$/', $line, $match))
			{
				// File references
				$refs[] = $match[1];
			}
			elseif (preg_match('/^msgid "(.*?)"$/', $line, $match))
			{
				if (isset($msgid))
				{
					// Store this message key/ID pair in the array along with references
					$msgs[$msgid] = array('refs' => $refs, 'vals' => array($msgstr));

					$msgstr = NULL;
				}

				$in = 'msgid';
				$msgid = $match[1];
			}
			elseif (preg_match('/^msgstr "(.*?)"$/', $line, $match))
			{
				$in = 'msgstr';
				$msgstr = $match[1];
			}
			elseif (preg_match('/^"(.*?)"$/', $line, $match))
			{
				if ($in == 'msgstr')
				{
					// Multi-line message string
					$msgstr .= $match[1];
				}
				elseif ($in == 'msgid')
				{
					// Multi-line message ID
					$msgid .= $match[1];
				}
			}
		}

		if (isset($msgid))
		{
			// Store final message pair
			$msgs[$msgid] = array('refs' => $refs, 'vals' => array($msgstr));
		}

		return $msgs;
	}

	/**
	 * Constructs the Gettext singleton
	 *
	 * @return void
	 */
	protected function __construct()
	{
		Gettext::$config = Kohana::config('gettext');

		if (Gettext::$config['regenerate_var'] AND isset($_GET[Gettext::$config['regenerate_var']]))
		{
			// Regenerate language files
			Gettext::generate_po_files();
		}

		$cache = Cache::instance();

		$refresh = FALSE;

		if (Gettext::$config['auto_refresh'] !== FALSE)
		{
			$time = $cache->get('gettext-refresh');

			if ($time !== NULL AND time() > ($time + Gettext::$config['auto_refresh']))
			{
				// Force a refresh from language data expiring
				$refresh = TRUE;
			}
		}

		if (Gettext::$config['refresh_var'] AND isset($_GET[Gettext::$config['refresh_var']]))
		{
			// Force a refresh from get var
			$refresh = TRUE;
		}

		if ($refresh)
		{
			// Forcing a reload of language settings, otherwise Apache has to be restarted
			$time = time();

			foreach (Gettext::$config['locales'] as $locale)
			{
				$file = APPPATH.Gettext::$config['directory'].'/'.$locale.'/LC_MESSAGES/'.Gettext::$config['domain'];

				if (($old_time = $cache->get('gettext-refresh')) !== NULL)
				{
					// Delete old language file if it exists
					@unlink($file.'-'.$old_time.'.mo');
				}

				if (is_file($file.'.mo'))
				{
					// Copy default language file to a temporary language file to be loaded in
					copy($file.'.mo', $file.'-'.$time.'.mo');
				}
			}

			// Set new refresh value to current time
			$cache->set('gettext-refresh', $time, NULL, 0);
		}

		$domain = Gettext::$config['domain'];

		if (($time = $cache->get('gettext-refresh')) !== NULL)
		{
			// Grab temporary language file to use if one is set, otherwise use default
			$domain .= '-'.$time;
		}

		// Bind text domain
		bindtextdomain($domain, APPPATH.Kohana::config('gettext.directory'));
		textdomain($domain);

		// Grab current locale
		$locale = Kohana::config('locale.language');

		// Update environment to current locale
		putenv('LC_ALL='.$locale[0]);
	}
}

/**
 * Translation method, including replacing of values
 *
 * @param  string  message to translate
 * @param  array   keys => values to replace
 * @return string
 */
function __($string, array $values = NULL)
{
	$string = _($string);

	return empty($values) ? $string : strtr($string, $values);
}