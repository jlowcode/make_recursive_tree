<?php

// No direct access
defined('_JEXEC') or die('Restricted access');

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';


class PlgFabrik_ListMake_recursive_tree extends PlgFabrik_List
{
    public function button(&$args)
    {
        $table = $this->getModel()->getTable()->db_table_name;
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('id')->from($table);
        $db->setQuery($query);
        $ids = $db->loadColumn();

        foreach($ids as $id) {
            $this->_process($id);
        }

        echo "Relacionamentos criados com sucesso!";
        exit();
    }

    public function deleteConnections($rowId, $table) {
        $db = FabrikWorker::getDbo();
        $db->setQuery("DELETE FROM " . $table . " WHERE parent_id = " . $rowId);
        $db->execute();
    }

    public function insertConnection($table, $parent_id, $dado, $column_name) {
        $obj = array();
        $obj["id"] = 0;
        $obj["parent_id"] = $parent_id;
        $obj[$column_name] = $dado;
        $obj = (Object)$obj;
        $insert = JFactory::getDbo()->insertObject($table, $obj, "id");
    }

    public function getParent($id, $table, $join_key, $parent_column) {
        $db = FabrikWorker::getDbo();
        $query = $db->getQuery(true);
        $query->select($parent_column)->from($table)->where($join_key . " = '" . $id . "'");
        $db->setQuery($query);

        return $db->loadResult();
    }

    public function doConnections($id_dado, $origem, $destino, $rowId, $table) {
        if (!$id_dado) {
            return;
        }
        else {
            $this->insertConnection($table . "_repeat_" . $destino->name, $rowId, $id_dado, $destino->name);
            $parent = $this->getParent($id_dado, $origem->params->join_db_name, $origem->params->join_key_column, $origem->params->tree_parent_id);
            $this->doConnections($parent, $origem, $destino, $rowId, $table);
        }
    }

    public function _process($id) {
        $db = JFactory::getDbo();
        $params = $this->getParams();
        $table = $this->getModel()->getTable()->db_table_name;
        $plugin = FabrikWorker::getPluginManager();

        $elementos_origem = json_decode($params->get('list_elemento_origem'))->elemento_origem;
        $elementos_destino = json_decode($params->get('list_elemento_destino'))->elemento_destino;

        for ($i=0; $i<count($elementos_destino); $i++) {
            $elemento_origem = $plugin->getElementPlugin($elementos_origem[$i])->getElement(true);
            $elemento_destino = $plugin->getElementPlugin($elementos_destino[$i])->getElement(true);
            $elemento_origem->params = json_decode($elemento_origem->params);
            $elemento_destino->params = json_decode($elemento_destino->params);

            $query = $db->getQuery(true);
            if (($elemento_origem->params->database_join_display_type === 'multilist') || ($elemento_origem->params->database_join_display_type === 'checkbox')) {
                $query->select($elemento_origem->name)->from($table . '_repeat_' . $elemento_origem->name)->where('parent_id = ' . (int)$id);
            }
            else {
                $query->select($elemento_origem->name)->from($table)->where('id = ' . (int)$id);
            }
            $db->setQuery($query);
            $data = $db->loadColumn();

            if ($data) {
                $this->deleteConnections($id, $table . "_repeat_" . $elemento_destino->name);
                foreach ($data as $item) {
                    $this->doConnections($item, $elemento_origem, $elemento_destino, $id, $table);
                }
            }
        }
    }


}
