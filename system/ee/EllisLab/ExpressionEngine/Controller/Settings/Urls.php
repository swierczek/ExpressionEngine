<?php

namespace EllisLab\ExpressionEngine\Controller\Settings;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use CP_Controller;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP URLs Settings Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Urls extends Settings {

	/**
	 * General Settings
	 */
	public function index()
	{
		$vars['sections'] = array(
			array(
				array(
					'title' => 'site_index',
					'desc' => 'site_index_desc',
					'fields' => array(
						'site_index' => array('type' => 'text')
					)
				),
				array(
					'title' => 'site_url',
					'desc' => 'site_url_desc',
					'fields' => array(
						'site_url' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'cp_url',
					'desc' => 'cp_url_desc',
					'fields' => array(
						'cp_url' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'themes_url',
					'desc' => 'themes_url_desc',
					'fields' => array(
						'theme_folder_url' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'themes_path',
					'desc' => 'themes_path_desc',
					'fields' => array(
						'theme_folder_path' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'docs_url',
					'desc' => 'docs_url_desc',
					'fields' => array(
						'doc_url' => array('type' => 'text')
					)
				),
				array(
					'title' => 'member_segment_trigger',
					'desc' => 'member_segment_trigger_desc',
					'fields' => array(
						'profile_trigger' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'category_segment_trigger',
					'desc' => 'category_segment_trigger_desc',
					'fields' => array(
						'reserved_category_word' => array('type' => 'text')
					)
				),
				array(
					'title' => 'category_url',
					'desc' => 'category_url_desc',
					'fields' => array(
						'use_category_name' =>	array(
							'type' => 'select',
							'choices' => array(
								'y' => lang('category_url_opt_titles'),
								'n' => lang('category_url_opt_ids')
							)
						)
					)
				),
				array(
					'title' => 'url_title_separator',
					'desc' => 'url_title_separator_desc',
					'fields' => array(
						'word_separator' =>	array(
							'type' => 'select',
							'choices' => array(
								'dash' => lang('url_title_separator_opt_hyphen'),
								'underscore' => lang('url_title_separator_opt_under')
							)
						)
					)
				),
			)
		);

		ee()->form_validation->set_rules(array(
			array(
				'field' => 'site_index',
				'label' => 'lang:site_index',
				'rules' => 'strip_tags|valid_xss_check'
			),
			array(
				'field' => 'site_url',
				'label' => 'lang:site_url',
				'rules' => 'required|strip_tags|valid_xss_check'
			),
			array(
				'field' => 'cp_url',
				'label' => 'lang:cp_url',
				'rules' => 'required|strip_tags|valid_xss_check'
			),
			array(
				'field' => 'theme_folder_url',
				'label' => 'lang:themes_url',
				'rules' => 'required|strip_tags|valid_xss_check'
			),
			array(
				'field' => 'theme_folder_path',
				'label' => 'lang:themes_path',
				'rules' => 'required|strip_tags|valid_xss_check|file_exists'
			),
			array(
				'field' => 'profile_trigger',
				'label' => 'lang:member_segment_trigger',
				'rules' => 'required|alpha_dash'
			),
			array(
				'field' => 'doc_url',
				'label' => 'lang:docs_url',
				'rules' => 'strip_tags|valid_xss_check'
			),
		));

		ee()->form_validation->validateNonTextInputs($vars['sections']);

		$base_url = ee('CP/URL')->make('settings/urls');

		if (AJAX_REQUEST)
		{
			ee()->form_validation->run_ajax();
			exit;
		}
		elseif (ee()->form_validation->run() !== FALSE)
		{
			if ($this->saveSettings($vars['sections']))
			{
				ee()->view->set_message('success', lang('preferences_updated'), lang('preferences_updated_desc'), TRUE);
			}

			ee()->functions->redirect($base_url);
		}
		elseif (ee()->form_validation->errors_exist())
		{
			ee()->view->set_message('issue', lang('settings_save_error'), lang('settings_save_error_desc'));
		}

		ee()->view->base_url = $base_url;
		ee()->view->ajax_validate = TRUE;
		ee()->view->cp_page_title = lang('url_path_settings');
		ee()->view->cp_page_title_alt = lang('url_path_settings_title');
		ee()->view->save_btn_text = 'btn_save_settings';
		ee()->view->save_btn_text_working = 'btn_saving';
		ee()->cp->render('settings/form', $vars);
	}
}
// END CLASS

/* End of file Urls.php */
/* Location: ./system/EllisLab/ExpressionEngine/Controller/Settings/Urls.php */