<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// include base class
if (! class_exists('Pro_variables_base')) {
    require_once(PATH_ADDONS . 'pro_variables/base.pro_variables.php');
}

/**
 * Pro Variables UPD class
 *
 * @package        pro_variables
 * @author         EEHarbor
 * @link           https://eeharbor.com/pro-variables
 * @copyright      Copyright (c) 2009-2022, EEHarbor
 */
class Pro_variables_upd
{
    // Use the base trait
    use Pro_variables_base;

    // --------------------------------------------------------------------
    // PROPERTIES
    // --------------------------------------------------------------------

    /**
     * Actions
     *
     * @var        array
     * @access     private
     */
    private $actions = array(
        array('Pro_variables', 'sync')
    );

    /**
     * Extension hooks
     *
     * @var        array
     * @access     private
     */
    private $hooks = array(
        'sessions_end',
        'template_fetch_template'
    );

    // --------------------------------------------------------------------
    // METHODS
    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @access     public
     * @return     void
     */
    public function __construct()
    {
        // Initialize base data for addon
        $this->initializeBaseData();

        if (ee('Filesystem')->isDir(PATH_THIRD . 'reupdate')) {
            ee()->load->library('logger');
            ee()->logger->developer('pro vars is in third party addon folder. please remove ', true, 1209600);
        }
    }

    /**
     * Install the module
     *
     * @access      public
     * @return      bool
     */
    public function install()
    {
        // If low vars is installed, we'll migrate from low vars to pro vars
        $lowVars = ee('Addon')->get('low_variables');
        if ($lowVars && $lowVars->isInstalled()) {
            $this->migrateFromLow();

            return true;
        }

        // --------------------------------------
        // Install tables
        // --------------------------------------

        foreach ($this->models as $model) {
            ee()->$model->install();
        }

        // --------------------------------------
        // Add row to modules table
        // --------------------------------------

        ee()->db->insert('exp_modules', array(
            'module_name'    => $this->class_name,
            'module_version' => $this->version,
            'has_cp_backend' => 'y'
        ));

        // --------------------------------------
        // Add actions
        // --------------------------------------

        foreach ($this->actions as $action) {
            $this->_add_action($action);
        }

        // --------------------------------------
        // Add hooks
        // --------------------------------------

        foreach ($this->hooks as $hook) {
            $this->_add_hook($hook);
        }

        // --------------------------------------
        // Register content type
        // --------------------------------------

        $this->register();

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Uninstall the module
     *
     * @return  bool
     */
    public function uninstall()
    {
        // --------------------------------------
        // get module id
        // --------------------------------------

        $query = ee()->db
            ->select('module_id')
            ->from('modules')
            ->where('module_name', $this->class_name)
            ->get();

        // --------------------------------------
        // remove references from module_member_groups
        // --------------------------------------

        ee()->db->where('module_id', $query->row('module_id'));
        ee()->db->delete('module_member_roles');

        // --------------------------------------
        // remove references from modules
        // --------------------------------------

        ee()->db->where('module_name', $this->class_name);
        ee()->db->delete('modules');

        // --------------------------------------
        // remove references from actions
        // --------------------------------------

        ee()->db->where_in('class', array($this->class_name, $this->class_name . '_mcp'));
        ee()->db->delete('actions');

        // --------------------------------------
        // remove references from extensions
        // --------------------------------------

        ee()->db->where('class', $this->class_name . '_ext');
        ee()->db->delete('extensions');

        // --------------------------------------
        // Uninstall tables
        // --------------------------------------

        foreach ($this->models as $model) {
            ee()->$model->uninstall();
        }

        // --------------------------------------
        // Unregister content type
        // --------------------------------------

        $this->unregister();

        return true;
    }

    public function migrateFromLow()
    {
        // --------------------------------------
        // Rename the LV tables
        // --------------------------------------
        ee()->load->library('smartforge');
        foreach ($this->models as $model) {
            $pvTable = ee()->$model->table();
            $pvTable = str_replace(ee()->db->dbprefix, '', $pvTable);
            $lvTable = str_replace('pro', 'low', $pvTable);

            if (ee()->db->table_exists($lvTable)) {
                ee()->smartforge->rename_table($lvTable, $pvTable);
            } else {
                ee()->$model->install();
            }
        }

        // --------------------------------------
        // Update modules
        // --------------------------------------
        ee()->db->update(
            'modules',
            ['module_name' => $this->class_name],
            ['module_name' => 'Low_variables'],
        );

        // --------------------------------------
        // Update actions
        // --------------------------------------
        ee()->db->update(
            'actions',
            ['class' => $this->class_name],
            ['class' => 'Low_variables'],
        );
        ee()->db->update(
            'actions',
            ['class' => $this->class_name],
            ['class' => 'Low_variables_mcp'],
        );

        // --------------------------------------
        // Update extensions
        // --------------------------------------
        ee()->db->update(
            'extensions',
            ['class' => $this->class_name . '_ext'],
            ['class' => 'Low_variables_ext'],
        );

        // --------------------------------------
        // Migrate the FT
        // --------------------------------------
        ee()->db->update(
            'fieldtypes',
            ['name' => strtolower($this->class_name)],
            ['name' => 'low_variables'],
        );

        // Migrate active FTs
        ee()->db->update(
            'channel_fields',
            ['field_type' => strtolower($this->class_name)],
            ['field_type' => 'low_variables'],
        );

        // Migrate settings
        $settings = ee()->pro_variables_settings->get();
        foreach ($settings['enabled_types'] as $k => $v) {
            $settings['enabled_types'][$k] = str_replace('low_', 'pro_', $v);
        }

        ee()->db->update(
            'extensions',
            ['settings' => serialize($settings)],
            ['class' => $this->class_name . '_ext'],
        );
    }

    // --------------------------------------------------------------------

    /**
     * Update the module
     *
     * @return  bool
     */
    public function update($current = '')
    {
        // -------------------------------------
        //  Same version? A-okay, daddy-o!
        // -------------------------------------

        if ($current == '' or version_compare($current, $this->version) === 0) {
            return false;
        }

        // Extension data
        $ext_data = array('version' => $this->version);

        // -------------------------------------
        //  Upgrade to 1.2.5
        // -------------------------------------

        if (version_compare($current, '1.2.5', '<')) {
            $settings = ee()->pro_variables_settings->get();
            $settings['enabled_types'] = array_keys(ee()->pro_variables_types->load_all());

            $ext_data['settings'] = serialize($settings);
        }

        // -------------------------------------
        //  Upgrade to 1.3.2
        // -------------------------------------

        if (version_compare($current, '1.3.2', '<')) {
            $this->_v132();
        }

        // -------------------------------------
        //  Upgrade to 1.3.4
        // -------------------------------------

        if (version_compare($current, '1.3.4', '<')) {
            $this->_v134();
        }

        // -------------------------------------
        //  Upgrade to 2.0.0
        // -------------------------------------

        if (version_compare($current, '2.0.0', '<')) {
            $this->_v200();
        }

        // -------------------------------------
        //  Upgrade to 2.0.0
        // -------------------------------------

        if (version_compare($current, '2.1.0', '<')) {
            $this->_add_hook('template_fetch_template');
        }

        // -------------------------------------
        //  Upgrade to 2.5.2
        // ------------------------------------

        if (version_compare($current, '2.6.0', '<')) {
            $this->_add_action($this->actions[0]);
        }

        // -------------------------------------
        //  Upgrade to 3.0.0
        // ------------------------------------

        if (version_compare($current, '3.0.0', '<')) {
            $this->register();
        }

        // -------------------------------------
        //  Upgrade to 5.0.0
        // ------------------------------------

        if (version_compare($current, '5.0.0', '<')) {
            // Check to see if pro variables is in the user folder. If so, leave a developer log item
            if (ee('Filesystem')->isDir(PATH_THIRD . 'pro_variables')) {
                echo "<pre>";
                var_dump('log it');
                exit;
            }
        }

        // Update the extension and fieldtype in the DB
        ee()->db->update('extensions', $ext_data, "class = '{$this->class_name}_ext'");
        ee()->db->update('fieldtypes', array('version' => $this->version), "name = '{$this->package}'");

        // Return TRUE to update version number in DB
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Do update to 1.3.2
     */
    private function _v132()
    {
        // Add group_id foreign key in table
        ee()->db->query("ALTER TABLE `exp_pro_variables` ADD `group_id` INT(6) UNSIGNED default 0 NOT NULL AFTER `variable_id`");

        // Create group table
        ee()->pro_variables_group_model->install();

        // Pre-populate groups, only if settings are found
        $settings = ee()->pro_variables_settings->get();

        // Do not pre-populate groups if group settings was not Y
        if (isset($settings['group']) && $settings['group'] != 'y') {
            return;
        }

        // Initiate groups array
        $groups = array();

        // Get all variables that have a pro variables reference
        $query = ee()->db->query("SELECT ee.variable_id as var_id, ee.variable_name as var_name, ee.site_id
            FROM exp_global_variables as ee, exp_pro_variables as pro
            WHERE ee.variable_id = pro.variable_id");

        // Loop through each variable, see if group applies
        foreach ($query->result_array() as $row) {
            // strip off prefix
            if (! empty($settings['prefix'])) {
                $row['var_name'] = preg_replace('#^' . preg_quote($settings['prefix']) . '_#', '', $row['var_name']);
            }

            // Get faux group name
            $tmp = explode('_', $row['var_name'], 2);
            $group = $tmp[0];
            unset($tmp);

            // Create new group if it does not exist
            if (! array_key_exists($group, $groups)) {
                $groups[$group] = ee()->pro_variables_group_model->insert(array(
                    'group_label' => ucfirst($group),
                    'site_id' => $row['site_id']
                ));
            }

            // Update Pro Variable
            ee()->pro_variables_variable_model->update($row['var_id'], array(
                'group_id' => $groups[$group]
            ));
        }
    }

    // --------------------------------------------------------------------

    /**
     * Do update to 1.3.4
     */
    private function _v134()
    {
        // Add is_hidden field to table
        ee()->db->query("ALTER TABLE `exp_pro_variables` ADD `is_hidden` CHAR(1) NOT NULL DEFAULT 'n'");

        // Set new attribute, only if settings are found
        $settings = ee()->pro_variables_settings->get();

        // Only update variables if prefix was filled in
        if (! empty($settings['prefix'])) {
            // Get prefix length
            $length = strlen($settings['prefix']);
            $prefix = ee()->db->escape_str($settings['prefix']);

            // Get vars with prefix
            $sql = "SELECT variable_id FROM `exp_global_variables` WHERE LEFT(variable_name, {$length}) = '{$prefix}'";
            $query = ee()->db->query($sql);

            // If there are IDs, show/hide them
            if ($ids = pro_flatten_results($query->result_array(), 'variable_id')) {
                // Hide wich vars
                $sql_in = (@$settings['with_prefixed'] == 'show') ? 'where_not_in' : 'where_in';

                // Execute query
                ee()->db->$sql_in('variable_id', $ids);
                ee()->db->update(ee()->pro_variables_variable_model->table(), array('is_hidden' => 'y'));
            }
        }

        // Update settings
        unset($settings['prefix'], $settings['with_prefixed'], $settings['ignore_prefixes']);
        ee()->db->update('extensions', array('settings' => serialize($settings)), "class = '" . $this->class_name . "_ext'");
    }

    // --------------------------------------------------------------------

    /**
     * Do update to 2.0.0
     */
    private function _v200()
    {
        // Add extra table attrs
        ee()->db->query("ALTER TABLE `exp_pro_variables` ADD `save_as_file` char(1) NOT NULL DEFAULT 'n'");
        ee()->db->query("ALTER TABLE `exp_pro_variables` ADD `edit_date` int(10) unsigned default 0 NOT NULL");

        // Change settings to smaller array
        $query = ee()->db->select('variable_id, variable_type, variable_settings')->from('pro_variables')->get();

        foreach ($query->result_array() as $row) {
            $settings = unserialize($row['variable_settings']);
            $settings = base64_encode(serialize($settings[$row['variable_type']]));

            ee()->db->where('variable_id', $row['variable_id']);
            ee()->db->update('pro_variables', array('variable_settings' => $settings));
        }
    }

    // --------------------------------------------------------------------

    /**
     * Add action
     *
     * @access     private
     * @param      array
     * @return     void
     */
    private function _add_action($action)
    {
        list($class, $method) = $action;

        ee()->db->insert('actions', array(
            'class'  => $class,
            'method' => $method
        ));
    }

    // --------------------------------------------------------------------

    /**
     * Add extension hook
     *
     * @access     private
     * @param      string
     * @return     void
     */
    private function _add_hook($name)
    {
        ee()->db->insert(
            'extensions',
            array(
                'class'    => $this->class_name . '_ext',
                'method'   => $name,
                'hook'     => $name,
                'settings' => serialize(ee()->pro_variables_settings->get()),
                'priority' => 2,
                'version'  => $this->version,
                'enabled'  => 'y'
            )
        );
    }

    // --------------------------------------------------------------------

    /**
     * Register LV as content type
     */
    private function register()
    {
        ee()->load->library('content_types');
        ee()->content_types->register($this->package);
    }

    /**
     * Unregister LV as content type
     */
    private function unregister()
    {
        ee()->load->library('content_types');
        ee()->content_types->unregister($this->package);
    }

    // --------------------------------------------------------------------
}
// End Class Pro_variables_upd

/* End of file upd.pro_variables.php */
