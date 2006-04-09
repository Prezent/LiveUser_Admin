<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * A framework for authentication and authorization in PHP applications
 *
 * LiveUser_Admin is meant to be used with the LiveUser package.
 * It is composed of all the classes necessary to administrate
 * data used by LiveUser.
 *
 * You'll be able to add/edit/delete/get things like:
 * * Rights
 * * Users
 * * Groups
 * * Areas
 * * Applications
 * * Subgroups
 * * ImpliedRights
 *
 * And all other entities within LiveUser.
 *
 * At the moment we support the following storage containers:
 * * DB
 * * MDB
 * * MDB2
 *
 * But it takes no time to write up your own storage container,
 * so if you like to use native mysql functions straight, then it's possible
 * to do so in under a hour!
 *
 * PHP version 4 and 5
 *
 * LICENSE: This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston,
 * MA  02111-1307  USA
 *
 *
 * @category authentication
 * @package LiveUser_Admin
 * @author Markus Wolff <wolff@21st.de>
 * @author  Helgi �ormar �orbj�rnsson <dufuz@php.net>
 * @author Lukas Smith <smith@pooteeweet.org>
 * @author  Arnaud Limbourg <arnaud@php.net>
 * @author Christian Dickmann <dickmann@php.net>
 * @author Matt Scifo <mscifo@php.net>
 * @author Bjoern Kraus <krausbn@php.net>
 * @copyright 2002-2006 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version CVS: $Id$
 * @link http://pear.php.net/LiveUser_Admin
 */

/**
 * Require the parent class definition
 */
require_once 'LiveUser/Admin/Perm/Medium.php';

/**
 * Complex permission administration class that extends the Medium class with the
 * ability to manage subgroups, implied rights and area admins
 *
 * This class provides a set of functions for implementing a user
 * permission management system on live websites. All authorisation
 * backends/containers must be extensions of this base class.
 *
 * @category authentication
 * @package LiveUser_Admin
 * @author  Christian Dickmann <dickmann@php.net>
 * @author  Markus Wolff <wolff@21st.de>
 * @author  Matt Scifo <mscifo@php.net>
 * @author Helgi �rmar �rbj�nsson <dufuz@php.net>
 * @copyright 2002-2006 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version Release: @package_version@
 * @link http://pear.php.net/LiveUser_Admin
 */
class LiveUser_Admin_Perm_Complex extends LiveUser_Admin_Perm_Medium
{
    /**
     * Constructor
     *
     * @return void
     *
     * @access protected
     */
    function LiveUser_Admin_Perm_Complex()
    {
        $this->LiveUser_Admin_Perm_Medium();
        $this->selectable_tables['getRights'][] = 'right_implied';
        $this->selectable_tables['getAreas'][] = 'area_admin_areas';
        $this->selectable_tables['getGroups'][] = 'group_subgroups';
    }

    /**
     * Assign subgroup to parent group.
     *
     * First checks if groupId and subgroupId are the same then if
     * the child group is already assigned to the parent group and last if
     * the child group does have a parent group already assigned to it.
     * (Just to difference between what kinda error was hit)
     *
     * If so it returns false and pushes the error into stack.
     *
     * The expected parameter array is of the form
     * <code>
     * $lua->perm->assignSubGroup(
     *     array('group_id' => 'id', 'subgroup_id' => 'id')
     * );
     * </code>
     *
     * @param array containing the subgroup_id and group_id
     * @return bool false on error, true on success
     *
     * @access public
     */
    function assignSubGroup($data)
    {
        if ($data['subgroup_id'] == $data['group_id']) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'Parent group id is the same as the subgroup id')
            );
            return false;
        }

        $filter = array('subgroup_id' => $data['subgroup_id']);
        $result = $this->_storage->selectCount('group_subgroups', 'group_id', $filter);
        if ($result === false) {
            return false;
        }

        if ($result == $data['group_id']) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'This child group is already a Parent of this group')
            );
            return false;
        }

        $result = $this->_storage->insert('group_subgroups', $data);
        // notify observer
        return $result;
    }

    /**
     * Unassign subgroup(s) from group(s)
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all groups will be affected by the remove
     * @return int|bool false on error, the affected rows on success
     *
     * @access public
     */
    function unassignSubGroup($filters)
    {
        $result = $this->_storage->delete('group_subgroups', $filters);
        // notify observer
        return $result;
    }

    /**
     * Imply Right
     *
     * @param array containing the implied_right_id and right_id
     * @return bool false on error, true on success
     *
     * @access public
     */
    function implyRight($data)
    {
        if (array_key_exists('right_id', $data) && array_key_exists('implied_right_id', $data)
            && $data['implied_right_id'] == $data['right_id']
        ) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'Right id is the same as the implied right id')
            );
            return false;
        }

        $params = array(
            'fields' => array(
                'right_id'
            ),
            'filters' => array(
                'implied_right_id' => $data['implied_right_id'],
                'right_id' => $data['right_id']
            )
        );

        $result = $this->_getImpliedRight($params);
        if ($result === false) {
            return false;
        }

        if (!empty($result)) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'This implied right is already implied from this right')
            );
            return false;
        }

        $result = $this->_storage->insert('right_implied', $data);
        if ($result === false) {
            return false;
        }

        $filter = array('right_id' => $data['right_id']);
        $this->_updateImpliedStatus($filter);

        // notify observer
        return $result;
    }

    /**
     * Unimply right(s)
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all groups will be affected by the remove
     * @param bool determines if the implied rights field in the rights table
     *                should be updated or not
     * @return int|bool false on error, the affected rows on success
     *
     * @access public
     */
    function unimplyRight($filters, $update = true)
    {
        $implied_filters = $this->_makeRemoveFilter($filters, 'implied_right_id', 'getRights');
        if (!$implied_filters) {
            return $implied_filters;
        }

        if ($update) {
            $right_filters = $this->_makeRemoveFilter($filters, 'right_id', 'getRights');
            if (!$right_filters) {
                return $right_filters;
            }
        }

        $result = $this->_storage->delete('right_implied', $implied_filters);
        if ($result === false) {
            return false;
        }

        if ($update) {
            $this->_updateImpliedStatus($right_filters);
        }

        // notify observer
        return $result;
    }

    /**
     * Add Area Admin
     *
     * @param array containing the area_id and perm_user_id
     * @return bool false on error, true on success
     *
     * @access public
     */
    function addAreaAdmin($data)
    {
        // needs more sanity checking, check if the perm_id is really perm_type 3 and so on
        // make sure when removing rights or updating them that if the user goes down
        // below perm_type 3 that a entry from area_admin_areas is removed

        if (!is_numeric($data['area_id'])) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR_DATA, 'exception',
                array('key' => 'area_id')
            );
            return false;
        }

        if (!is_numeric($data['perm_user_id'])) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR_DATA, 'exception',
                array('key' => 'perm_user_id')
            );
            return false;
        }

        $params = array(
            'fields' => array(
                'perm_type'
            ),
            'filters' => array(
                'perm_user_id' => $data['perm_user_id']
            ),
            'select' => 'row',
        );

        $result = parent::getUsers($params);
        if ($result === false) {
            return false;
        }

        if (!array_key_exists('perm_type', $result) || $result['perm_type'] < 3) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'The user doesn\'t have sufficient rights')
            );
            return false;
        }

        $result = $this->_storage->insert('area_admin_areas', $data);

        // notify observer
        return $result;
    }

    /**
     * Remove Area Admin(s)
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all groups will be affected by the remove
     * @return int|bool false on error, the affected rows on success
     *
     * @access public
     */
    function removeAreaAdmin($filters)
    {
        $result = $this->_storage->delete('area_admin_areas', $filters);
        if ($result === false) {
            return false;
        }

        // notify observer
        return $result;
    }

    /**
     * Remove areas and all their relevant relations
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all areas will be affected by the remove
     * @return int|bool false on error, the affected rows on success
     *
     * @access public
     */
    function removeArea($filters)
    {
        $filters = $this->_makeRemoveFilter($filters, 'area_id', 'getAreas');
        if (!$filters) {
            return $filters;
        }

        $result = $this->removeAreaAdmin($filters);
        if ($result === false) {
            return false;
        }

        $result = parent::removeArea($filters);

        // notify observer
        return $result;
    }

    /**
     * Remove rights and all their relevant relations
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all rights will be affected by the remove
     * @return int|bool false on error, the affected rows on success
     *
     * @access public
     */
    function removeRight($filters)
    {
        $result = $this->unimplyRight($filters, false);
        if ($result === false) {
            return false;
        }

        $result = parent::removeRight($filters);
        if ($result === false) {
            return false;
        }

        $this->_updateImpliedStatus($filters);

        // notify observer
        return $result;
    }

    /**
     * Get SubGroups
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     * @return bool|array false on failure or array with selected data
     *
     * @access private
     */
    function _getSubGroups($params = array())
    {
        $selectable_tables = array('group_subgroups');
        $root_table = 'group_subgroups';

        $data = $this->_makeGet($params, $root_table, $selectable_tables);
        return $data;
    }

    /**
     * Get Implied Rights
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     * @return bool|array false on failure or array with selected data
     *
     * @access private
     */
    function _getImpliedRight($params = array())
    {
        $selectable_tables = array('right_implied');
        $root_table = 'right_implied';

        $data = $this->_makeGet($params, $root_table, $selectable_tables);
        return $data;
    }

    /**
     * Remove groups and all their relevant relations
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all groups will be affected by the removed
     * @return int|bool false on error, the affected rows on success
     *
     * @access public
     */
    function removeGroup($filters)
    {
        if (array_key_exists('recursive', $filters)) {
            $param = array(
                'fields' => array(
                    'subgroup_id'
                ),
                'filters' => array(
                    'group_id' => $filters['group_id']
                )
            );
            $result = $this->_getSubGroups($param);
            if ($result === false) {
                return false;
            }

            foreach ($result as $subGroupId) {
                $filter = array('group_id' => $subGroupId['subgroup_id'], 'recursive' => true);
                $result = $this->removeGroup($filter);
                if ($result === false) {
                    return false;
                }
            }
            unset($filters['recursive']);
        }

        $result = $this->unassignSubGroup($filters);
        if ($result === false) {
            return false;
        }

        return parent::removeGroup($filters);
    }

    /**
     * Updates implied status
     *
     * @param array key values pairs (value may be a string or an array)
     *                      This will construct the WHERE clause of your update
     *                      Be careful, if you leave this blank no WHERE clause
     *                      will be used and all rights will be affected by the update
     * @return bool denotes success or failure
     *
     * @access private
     */
    function _updateImpliedStatus($filters)
    {
        $params = array(
            'fields' => array('right_id'),
            'filters' => $filters,
            'select' => 'col',
        );

        $rights = $this->getRights($params);
        if ($rights === false) {
            return false;
        }

        $filters = array('right_id' => $rights);

        $count = $this->_storage->selectCount('right_implied', 'right_id', $filters);
        if ($count === false) {
            return false;
        }

        $data = array('has_implied' => (bool)$count);

        $result = $this->updateRight($data, $filters);
        if ($result === false) {
            return false;
        }

        // notify observer
        return $result;
    }

    /**
     * Get parent of a subgroup
     *
     * @param Id of the subgroup_id that is used to fetch the parent
     * @return bool|int false on failure or integer with the parent group_id
     *
     * @access public
     */
    function getParentGroup($subGroupId)
    {
        if (!is_numeric($subGroupId)) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'Something wrong with your param, make sure its a
                               numeric value and not empty')
            );
            return false;
        }

        $params = array(
            'fields' => array(
                'group_id'
            ),
            'filters' => array(
                'subgroup_id' => $subGroupId
            ),
            'select' => 'one'
        );
        $result = $this->_getSubGroups($params);

        return $result;
    }

    /**
     * Fetches groups
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     *                 'selectable_tables' - array list of tables that may be
     *                             joined to in this query, the first element is
     *                             the root table from which the joins are done
     *                 'subgroups' - filter array if all subgroups should
                                   should be fetched into a flat array
     *                 'hierarchy' - filter array if all subgroups should
                                   should be fetched into a nested array
     *
     *    note that 'hierarchy' requires 'rekey' enabled, 'group' is disabled,
     *    'select' set to 'all' and the first field needs to be 'group_id'
     * @return bool|array false on failure or array with selected data
     *
     * @access public
     */
    function getGroups($params = array())
    {
        if (!array_key_exists('subgroups', $params)
            && !array_key_exists('hierarchy', $params)
        ) {
            return parent::getGroups($params);
        }

        if (array_key_exists('select', $params)
            && ($params['select'] == 'one' || $params['select'] == 'row')
        ) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => 'Setting "subgroups" or "hierarchy" requires select to be set to "col" or "all"')
            );
            return false;
        }

        if (array_key_exists('hierarchy', $params)) {
            return $this->_getGroupsWithHierarchy($params);
        }

        return $this->_getGroupsWithSubgroups($params);
    }

    /**
     * Fetches groups with their subgroups into a flat structure
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     *                 'selectable_tables' - array list of tables that may be
     *                             joined to in this query, the first element is
     *                             the root table from which the joins are done
     *                 'subgroups' - filter array if all subgroups should
                                   should be fetched into a flat array
     * @return bool|array false on failure or array with selected data
     *
     * @access private
     */
    function _getGroupsWithSubgroups($params)
    {
        $subgroups = is_array($params['subgroups']) ? $params['subgroups'] : array();

        $tmp_params = array(
            'fields' => array('group_id'),
            'select' => 'col',
            'filters' => $subgroups,
        );

        $result = parent::getGroups($tmp_params);
        if (!$result) {
            return $result;
        }

        $subgroup_ids = $result;

        do {
            $tmp_params = array(
                'fields' => array(
                    'subgroup_id',
                ),
                'filters' => $subgroups,
                'select' => 'col',
            );

            $tmp_params['filters']['subgroup_id'] = array(
                'op' => 'NOT IN',
                'value' => $result,
            );

            if (array_key_exists('group_id', $tmp_params['filters'])
                && (!is_array($params['filters']['group_id']) || !array_key_exists('value', $params['filters']['group_id']))
            ) {
                $tmp_params['filters']['group_id'] = array_intersect($subgroup_ids, (array)$params['subgroups']['group_id']);
            } else {
                $tmp_params['filters']['group_id'] = $subgroup_ids;
            }

            $subgroup_ids = $this->_getSubGroups($tmp_params);
            if ($subgroup_ids === false) {
                return false;
            }

            $result = array_merge($result, (array)$subgroup_ids);
        } while(!empty($subgroup_ids));

        if (array_key_exists('filters', $params)
            && array_key_exists('group_id', $params['filters'])
            && (!is_array($params['filters']['group_id']) || !array_key_exists('value', $params['filters']['group_id']))
        ) {
            $params['filters']['group_id'] = array_intersect($result, (array)$params['filters']['group_id']);
        } else {
            $params['filters']['group_id'] = $result;
        }
        return parent::getGroups($params);
    }

    /**
     * Fetches groups with their subgroups into a hierarchal structure
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     *                 'selectable_tables' - array list of tables that may be
     *                             joined to in this query, the first element is
     *                             the root table from which the joins are done
     *                 'hierarchy' - filter array if all subgroups should
                                   should be fetched into a nested array
     * @return bool|array false on failure or array with selected data
     *
     * @access private
     */
    function _getGroupsWithHierarchy($params)
    {
        if ((!array_key_exists('rekey', $params) || !$params['rekey'])
            || (array_key_exists('group', $params) && $params['group'])
            || (array_key_exists('select', $params) && $params['select'] != 'all')
            || (array_key_exists('fields', $params) && reset($params['fields']) !== 'group_id')
        ) {
            $this->stack->push(
                LIVEUSER_ADMIN_ERROR, 'exception',
                array('msg' => "Setting 'hierarchy' is only allowed if 'rekey' is enabled, ".
                    "'group' is disabled, 'select' is 'all' and the first field is 'group_id'")
            );
            return false;
        }

        $groups = parent::getGroups($params);
        if (!$groups) {
            return $groups;
        }

        $tmp_params = array(
            'fields' => array(
                'group_id',
                'subgroup_id',
            ),
            'filters' => array('group_id' => array_keys($groups)),
            'rekey' => true,
            'group' => true,
        );

        $subgroups = $this->_getSubGroups($tmp_params);
        if ($subgroups === false) {
            return false;
        }

        $hierarchy = is_array($params['hierarchy']) ? $params['hierarchy'] : array();

        foreach ($subgroups as $group_id => $subgroup_ids) {
            $params['filters'] = $hierarchy;
            if (array_key_exists('group_id', $params['filters'])
                && (!is_array($params['filters']['group_id']) || !array_key_exists('value', $params['filters']['group_id']))
            ) {
                $params['filters']['group_id'] = array_intersect($subgroup_ids, (array)$params['filters']['group_id']);
            } else {
                $params['filters']['group_id'] = $subgroup_ids;
            }
            $subgroup_data = $this->_getGroupsWithHierarchy($params);
            if ($subgroup_data === false) {
                return false;
            }
            $groups[$group_id]['subgroups'] = $subgroup_data;
        }

        return $groups;
    }

    /**
     * Fetches rights
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     *                 'selectable_tables' - array list of tables that may be
     *                             joined to in this query, the first element is
     *                             the root table from which the joins are done
     *                 'by_group' - if joins should be done using the 'userrights'
     *                             (false default) or through the 'grouprights'
     *                             and 'groupusers' tables (true)
     *                 'inherited' - filter array to fetch all rughts from
                                    (sub)group membership
     *                 'implied'  - filter array for fetching implied rights
     *                 'hierarchy' - boolean if implied rights should be fetched
                                   into a nested array
     * @return bool|array false on failure or array with selected data
     *
     * @access public
     */
    function getRights($params = array())
    {
        $inherited = array_key_exists('inherited', $params);
        $hierarchy = array_key_exists('hierarchy', $params);
        $implied = ($hierarchy || array_key_exists('implied', $params));

        if ($inherited || $implied) {
            if ((!array_key_exists('rekey', $params) || !$params['rekey'])
                || (array_key_exists('group', $params) && $params['group'])
                || (array_key_exists('select', $params) && $params['select'] != 'all')
                || (array_key_exists('fields', $params) && reset($params['fields']) !== 'right_id')
            ) {
                $this->stack->push(
                    LIVEUSER_ADMIN_ERROR, 'exception',
                    array('msg' => "Setting 'implied' or 'inherited' is only allowed if 'rekey' is enabled, ".
                        "'group' is disabled, 'select' is 'all' and the first field is 'right_id'")
                );
                return false;
            }

            if ($implied
                && array_key_exists('fields', $params)
                && !in_array('has_implied', $params['fields'])
            ) {
                $this->stack->push(
                    LIVEUSER_ADMIN_ERROR, 'exception',
                    array('msg' => "Setting 'implied' requires that 'has_implied' field needs to be in the select list")
                );
                return false;
            }
        }

        // handle select, fields and rekey
        $rights = parent::getRights($params);
        if ($rights === false) {
            return false;
        }

        // if the result was empty or no additional work is needed
        if (empty($rights) || (!$inherited && !$implied)) {
            return $rights;
        }

        // read rights inherited by (sub)groups
        if ($inherited) {
            // todo: consider adding a NOT IN filter
            $inherited_rights = $this->_getInheritedRights($params);
            if ($inherited_rights === false) {
                return false;
            }

            if (!empty($inherited_rights)) {
                foreach ($inherited_rights as $right_id => $right) {
                    if (isset($rights[$right_id])) {
                        continue;
                    }

                    $right['_type'] = 'inherited';
                    $rights[$right_id] = $right;
                }
            }
        }

        if ($implied) {
            $_rights = $rights;
            $rights = array();

            foreach ($_rights as $right_id => $right) {
                if (!array_key_exists('_type', $right)) {
                    $right['_type'] = 'granted';
                }
                $rights[$right_id] = $right;
                if (!$right['has_implied']) {
                    continue;
                }

                // todo: consider adding a NOT IN filter
                $implied_rights = $this->_getImpliedRights($params, $right_id);
                if ($implied_rights === false) {
                    return false;
                } elseif (empty($implied_rights)) {
                    continue;
                }

                foreach ($implied_rights as $implied_right_id => $right) {
                    if (isset($rights[$implied_right_id])) {
                        continue;
                    }

                    $right['_type'] = 'implied';

                    if ($hierarchy) {
                        $rights[$right_id]['implied_rights'][$implied_right_id] = $right;
                    } else {
                        $rights[$implied_right_id] = $right;
                    }
                }
            }
        } elseif ((!array_key_exists('select', $params) || $params['select'] == 'all')
           && (!array_key_exists('fields', $params)
                || count($params['fields']) > 1
                || reset($params['fields']) === '*'
            )
        ) {
            foreach ($rights as $right_id => $right) {
                if (!isset($rights[$right_id]['_type']) || !$rights[$right_id]['_type']) {
                    $rights[$right_id]['_type'] = 'granted';
                }
            }
        }

        return $rights;
    }

    /**
     * Fetches implied rights for a given right
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     *                 'selectable_tables' - array list of tables that may be
     *                             joined to in this query, the first element is
     *                             the root table from which the joins are done
     *                 'by_group' - if joins should be done using the 'userrights'
     *                             (false default) or through the 'grouprights'
     *                             and 'groupusers' tables (true)
     *                 'implied'  - filter array for fetching implied rights
     * @return bool|array false on failure or array with selected data
     *
     * @access private
     */
    function _getImpliedRights($params, $right_id)
    {
        $selectable_tables = array('right_implied', 'rights');
        $root_table = 'right_implied';

        $param = array(
            'fields' => array('implied_right_id'),
            'select' => 'col',
            'filters' => array('right_id' => $right_id),
        );

        $result = $this->_makeGet($param, $root_table, $selectable_tables);
        if ($result === false) {
            return false;
        }

        $params['filters'] = is_array($params['implied']) ? $params['implied'] : array();
        if (array_key_exists('right_id', $params['filters'])
            && (!is_array($params['filters']['right_id']) || !array_key_exists('value', $params['filters']['right_id']))
        ) {
            $params['filters']['right_id'] = array_intersect($result, (array)$params['filters']['right_id']);
        } else {
            $params['filters']['right_id'] = $result;
        }
        return $this->getRights($params);
    }

    /**
     * Fetches all rights gained through subgroup memberships
     *
     * @param array containing key-value pairs for:
     *                 'fields'  - ordered array containing the fields to fetch
     *                             if empty all fields from the user table are fetched
     *                 'filters' - key values pairs (value may be a string or an array)
     *                 'orders'  - key value pairs (values 'ASC' or 'DESC')
     *                 'rekey'   - if set to true, returned array will have the
     *                             first column as its first dimension
     *                 'group'   - if set to true and $rekey is set to true, then
     *                             all values with the same first column will be
     *                             wrapped in an array
     *                 'limit'   - number of rows to select
     *                 'offset'  - first row to select
     *                 'select'  - determines what query method to use:
     *                             'one' -> queryOne, 'row' -> queryRow,
     *                             'col' -> queryCol, 'all' ->queryAll (default)
     *                 'selectable_tables' - array list of tables that may be
     *                             joined to in this query, the first element is
     *                             the root table from which the joins are done
     *                 'by_group' - if joins should be done using the 'userrights'
     *                             (false default) or through the 'grouprights'
     *                             and 'groupusers' tables (true)
     *                 'inherited' - filter array to fetch all rughts from
                                    (sub)group membership
     * @return bool|array false on failure or array with selected data
     *
     * @access private
     */
    function _getInheritedRights($params)
    {
        $param = array(
            'fields' => array('group_id'),
            'select' => 'col',
            'filters' => is_array($params['inherited']) ? $params['inherited'] : array(),
            'subgroups' => true,
        );

        $result = $this->getGroups($param);
        if ($result === false) {
            return false;
        } elseif (empty($result)) {
            return array();
        }

        if (array_key_exists('filters', $params)
            && array_key_exists('group_id', $params['filters'])
            && (!is_array($params['filters']['group_id']) || !array_key_exists('value', $params['filters']['group_id']))
        ) {
            $params['filters']['group_id'] = array_intersect($result, (array)$params['filters']['group_id']);
        } else {
            $params['filters']['group_id'] = $result;
        }
        $params['by_group'] = true;
        return $this->getRights($params);
    }
}
?>
