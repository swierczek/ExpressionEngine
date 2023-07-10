<?php

/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2023, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace ExpressionEngine\Updater\Version_7_4_0;

/**
 * Update
 */
class Updater
{
    public $version_suffix = '';

    /**
     * Do Update
     *
     * @return TRUE
     */
    public function do_update()
    {
        $steps = new \ProgressIterator(
            [
                'modifyMemberFieldTypeColumn',
                'addMemberManagerViewsTable',
                'addEditMemberFieldsPermission',
                'addRoleHighlightColumn'
            ]
        );

        foreach ($steps as $k => $v) {
            $this->$v();
        }

        return true;
    }

    public function modifyMemberFieldTypeColumn()
    {
        ee()->smartforge->modify_column(
            'member_fields',
            [
                'm_field_type' => [
                    'name' => 'm_field_type',
                    'type' => 'varchar',
                    'constraint' => 50,
                    'null' => false,
                    'default' => 'text'
                ]
            ]
        );
    }

    private function addMemberManagerViewsTable()
    {
        if (! ee()->db->table_exists('member_manager_views')) {
            ee()->dbforge->add_field(
                [
                    'view_id' => [
                        'type' => 'int',
                        'constraint' => 10,
                        'unsigned' => true,
                        'null' => false,
                        'auto_increment' => true
                    ],
                    'role_id' => [
                        'type' => 'int',
                        'constraint' => 6,
                        'unsigned' => true,
                        'null' => false,
                    ],
                    'member_id' => [
                        'type' => 'int',
                        'constraint' => 10,
                        'unsigned' => true,
                        'null' => false,
                    ],
                    'name' => [
                        'type' => 'varchar',
                        'constraint' => 128,
                        'null' => false,
                        'default' => '',
                    ],
                    'columns' => [
                        'type' => 'text',
                        'null' => false
                    ]
                ]
            );
            ee()->dbforge->add_key('view_id', true);
            ee()->dbforge->add_key(['role_id', 'member_id']);
            ee()->smartforge->create_table('member_manager_views');
        }
    }

    private function addEditMemberFieldsPermission()
    {
        $permissions = ee('db')->where('permission', 'can_admin_roles')->get('permissions');
        if ($permissions->num_rows() > 0) {
            foreach ($permissions->result_array() as $row) {
                $insert = [
                    'role_id' => $row['role_id'],
                    'site_id' => $row['site_id'],
                    'permission' => 'can_edit_member_fields'
                ];
                ee('db')->insert('permissions', $insert);
            }
        }
    }

    private function addRoleHighlightColumn()
    {
        if (! ee()->db->field_exists('highlight', 'roles')) {
            ee()->smartforge->add_column(
                'roles',
                array(
                    'highlight' => array(
                        'type' => 'varchar',
                        'constraint' => 6,
                        'default' => '',
                        'null' => false
                    )
                )
            );
        }
        return true;
    }
}

// EOF
