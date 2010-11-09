<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2010, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Home Page Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */
class Content extends CI_Controller {

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Content()
	{
		parent::CI_Controller();

		if ( ! $this->cp->allowed_group('can_access_content'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}
	}
	
	// --------------------------------------------------------------------

	/**
	 * Index function
	 *
	 * @access	public
	 * @return	void
	 */	
	function index()
	{
		if ( ! $this->cp->allowed_group('can_access_content'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->lang->loadfile('content');

		$this->cp->set_variable('cp_page_title', $this->lang->line('content'));
		
		$this->javascript->output($this->javascript->slidedown("#adminTemplatesSubmenu"));

		$this->javascript->compile();

		$this->load->vars(array('controller'=>'content'));

		$this->load->view('_shared/overview');
	}
	
	
}
// END CLASS

/* End of file content.php */
/* Location: ./system/expressionengine/controllers/cp/content.php */