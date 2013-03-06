<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2012, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * ExpressionEngine Relationship Class
 *
 *
 * Takes an array of field_ids that correspond to the ids of the
 * relationship fields that we need to pull entries from in the
 * relationship query. This array comes directly from the tag data. For
 * example, if we have a channel set up like the following:
 * 
 * Seasons
 * 	title
 * 	url_title
 * 	games		RELATIONSHIP (Games)
 * 	teams		RELATIONSHIP (Teams)
 * 
 * Games
 * 	title
 * 	url_title
 * 	home		RELATIONSHIP (Teams, 1)
 * 	away		RELATIONSHIP (Teams, 1)
 *
 * Teams
 * 	title
 * 	url_title
 * 	players		RELATIONSHIP (Players)
 * 
 * Players
 * 	title
 * 	url_title
 * 	first_name
 * 	last_name
 * 	number
 *
 * Then we might see tag data that looked like the following:
 * 	
 * 	{exp:channel:entries channel="Seasons"}
 * 		{games}
 * 			{games:home:title} vs {games:away:title}
 * 			{games:home:players}
 * 				{games:home:players:number} { games:home:players:first_name} {games:home:players:last_name}
 * 			{/games:home:players}
 * 			{games:away:players}
 * 				{games:away:players:number} {games:away:players:first_name} {games:away:players:last_name}
 * 			{games:away:players}
 * 		{/games}
 * 	{/exp:channel:entires}
 * 
 * 
 * Since the leaf tags also contain the names for each level above them,
 * we only need to pull the leaves out of the single_variables and from
 * that we can generate our array. In our above example, the leaves would
 * be the following:
 * 
 * {games:home:title}
 * {games:away:title}
 * {games:home:players;number}
 * {games:home:players:first_name}
 * {games:home:players:last_name}
 * {games:away:players:number}
 * {games:away:players:first_name}
 * {games:away:players:last_name}
 * 
 * Each section of those names corresponds to a field and thus a field_id.
 * We can replace the names with field_id and then explode to get arrays:
 * 
 * array(2, 3, 4)
 * array(2, 5, 6)
 * array(2, 3, 7, 8)
 * array(2, 3, 7, 9)
 * array(2, 3, 7, 10)
 * array(2, 5, 7, 8)
 * array(2, 5, 7, 9)
 * array(2, 5, 7, 10)
 * 
 * Since we're only interested in the relationship fields, we can trim off
 * the final field id. Then we can flip the matrix to we get an array of
 * the ids we need at each level of nesting and run array unique, so we
 * only get unique ids. We don't need to retain the information about
 * the tree structure, we only need the total list of the needed entries.
 * We still have information about the final tree structure, so we can
 * rebuild the tree from a generated list of entries using Pascal's tree
 * library. So our finally result array passed to build_level will look
 * like this:
 *
 * array(
 * 	array(2),
 *  array(3,5),
 * 	array(7)
 * )
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Core
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Relationships {

	private $_table = 'relationships';


 	// --------------------------------------------------------------------

	/**
	 * Get a relationship parser and query object, populated with the information
	 * we'll need to parse out the relationships in this template.
	 *
	 * @param int[] An array of entry ids for the entries we wish to pull the relationships of.
	 * @param int[] The rfields array from the Channel Module at the time of parsing.
	 * @param Template The template we are parsing.
	 * @return Relationship_Query The query object populated with the data queried from the database.
	 */
	public function get_relationship_parser(EE_Template $template, array $relationship_fields, array $custom_fields)
	{
		return new Relationship_Parser($template, $relationship_fields, $custom_fields);
	}

 	// --------------------------------------------------------------------

 	/**
 	 * Clear Cache For Certain Entries
 	 *
 	 * Selectively and intelligently clears the cache for a certain
 	 * entry or entries. This should be the most common use case.
 	 *
 	 * @param	entry_id
 	 *		- entry id or array of ids to clear
 	 *
 	 * @return	void
 	 */
 	public function clear_entry_cache($entry_id)
 	{
 		$db = $this->_isolate_db();

 		if (is_array($entry_id) && count($entry_id))
 		{
 			$db->where_in('rel_parent_id', $entry_id);
 			$db->or_where_in('rel_child_id', $entry_id);
 		}
 		else
 		{
 			$db->where('rel_parent_id', $entry_id);
 			$db->or_where('rel_child_id', $entry_id);
 		}

 		$db->set(array(
 			'rel_data' => '',
 			'reverse_rel_data' => ''
 		));

 		$db->update($this->_table);
 	}

 	// --------------------------------------------------------------------

 	/**
 	 * Clear Cache For Certain Channels
 	 *
 	 * Selectively clears the cache for all entries in a channel or set
 	 * of channels. Useful when changing custom fields.
 	 *
 	 * @param channel_id
 	 *		- channel id or array of ids to clear
 	 *
 	 * @return void
 	 */
 	public function clear_channel_cache($channel_id)
 	{
 		$db = $this->_isolate_db();
 		
 		$db->select('entry_id');

 		if (is_array($channel_id) && count($channel_id))
 		{
 			$db->where_in('channel_id', $channel_id);
 		}
 		else
 		{
 			$db->where('channel_id', $channel_id);
 		}

 		$entry_ids = $db->get('channel_titles')->result_array();

 		// only clear if we actually found any
 		if (count($entry_ids))
 		{
 			$this->clear_entry_cache(
 				array_map('array_pop', $entry_ids) // flattens array of single item arrays
 			);
 		}
 	}

 	// --------------------------------------------------------------------

 	/**
 	 * Clear All Relationship Caches
 	 *
 	 * Be very careful with this method. It can bring sites with a lot
 	 * of relationships to a grinding halt. Be smart about caching!
 	 *
 	 * @access	public
 	 * @return	void
 	 */
 	public function clear_all_caches()
 	{
 		$db = $this->_isolate_db();

 		$db->set(array(
 			'rel_data' => '',
 			'reverse_rel_data' => ''
 		));

 		$db->update($this->_table);
 	}

 	// --------------------------------------------------------------------

 	/**
 	 * Isolate Database
 	 *
 	 * Creates a new blank database object. This way we can do relationship
 	 * management in between other things and not worry about stepping on
 	 * toes on the CI db object.
 	 *
 	 * @return	CI active record object guaranteed to be blank
 	 */
 	public function _isolate_db()
 	{
 		$EE = get_instance();

 		$db = clone $EE->db;

 		$db->_reset_write();
 		$db->_reset_select();

 		return $db;
 	}
}

/**
 *
 */
class Relationship_Parser 
{
	protected $template = NULL;							// The Template that we are currently parsing Relationships for

	protected $custom_fields = array();					// Custom field id to name mapping
	protected $relationship_field_ids = array();		// Relationship field map (name => field_id)
	protected $relationship_field_names = array();		// Another relationship field map (field_id => name)

	/**
	 * An array of our relationship data in the format
	 * that EE_Template::parse_variables() expects it to 
	 * be in.
	 */	
	protected $variables = array();
	
	/**
	 * Create a relationship parser for the given Template.
	 */
	public function __construct(EE_Template $template, array $relationship_fields, array $custom_fields)
	{
		$this->template = $template;
		$this->custom_fields = $custom_fields;
		$this->relationship_field_ids = $relationship_fields;
		$this->relationship_field_names = array_flip($relationship_fields);
	}

	/**
	 * Check if a given tag name is a relationship field and if
	 * so return its id.
	 */
	protected function _get_relationship_field_id($tag_name)
	{
		if ( ! $tag_name)
		{
			return FALSE;
		}

		// last segment
		$tag_name = ':'.$tag_name;
		$tag_name = substr(strrchr($tag_name, ':'), 1);

		if (array_key_exists($tag_name, $this->relationship_field_ids))
		{
			return $this->relationship_field_ids[$tag_name];
		}

		if ($tag_name == 'sibling' ||
			$tag_name == 'parent' ||
			$tag_name == 'siblings' ||
			$tag_name == 'parents')
		{
			return $tag_name;
		}

		return FALSE;
	}

	/**
	 * Find All Relationships of the Given Entries in the Template 
	 *
	 * Searches the template the parser was constructed with for relationship
	 * tags and then builds a tree of all the requested related entries for
	 * each of the entries passed in the array.
	 *
	 * @param	int[]	An array of entry ids who's relations we need
	 *						to find.
	 */
	public function query_for_entries(array $entry_ids)
	{
		// hackity crackity tree thing coming up

		$str = $this->template->tagdata;

		// No variables?  No reason to continue...
		if (strpos($str, '{') === FALSE OR ! preg_match_all("/".LD."([^{]+?)".RD."/", $str, $matches))
		{
			return array();
		}




		//////////////////////////////////////////////
		// Note to self:							//
		// Proxy off the original uuid in the parse	//
		// tree and simply connect things to that.	//
		// Which means we will have:				//
		// - parse_tree								//
		// - db_ids_result	{uuid => @todo depth}	//
		// - db_data		{entry_id => [data]}	//
		//////////////////////////////////////////////






		// I have a love hate relationship with this.
		// I love that it works pretty easily, I hate that I
		// once again have to resort to a node-list => tree
		// strategy because building the tree directly was
		// four times uglier. Yes, four times.

		$reversed = array_reverse($matches[0]);
		unset($matches);

		$uuid = 0;
		$nodes = array();
		$id_stack = array();
		$tag_stack = array();

		foreach ($reversed as $tag)
		{
			$tag_name = substr($tag, 1, strcspn($tag, ' }', 1));

			$is_closing = ($tag_name[0] == '/');
			$tag_name = ltrim($tag_name, '/');

			$field_id = $this->_get_relationship_field_id($tag_name);

			if ( ! $field_id)
			{
				continue;
			}

			$uuid++;
			$parent_id = end($id_stack);

			if ($is_closing)
			{
				$id_stack[] = $uuid;
				$tag_stack[] = $tag_name;
			}
			elseif ($tag_name == end($tag_stack))
			{
				array_pop($tag_stack);
				$lookup_id = array_pop($id_stack);

				$params = get_instance()->functions->assign_parameters($tag);

				$nodes[$lookup_id]['params'] = $params ? $params : array();

				continue;
			}

			$nodes[$uuid] = array(
				'uuid' => $uuid,
				'name' => $tag_name,
				'parent_uuid' => $parent_id,
				'field_id'	=> $field_id
			);
		}

		// Doing our own parsing let's us do error checking
		if (count($tag_stack))
		{
			// going backwards has the unfortunate side effect that we end up
			// finding missmatched closing tags. Should be ok though - either
			// way you'll be in the template looking for pairs.
			throw new RuntimeException('Unmatched Closing Tag: "{/'.end($tag_stack).'}"');
		}

		// and load 'em up!
		get_instance()->load->library('datastructures/tree'); // make iterators available

		$tree = get_instance()->tree->load($nodes, array(
			'key' => 'uuid',
			'parent' => 'parent_uuid'
		));


		// Build the tree
		// @todo merge this in with the above

		$it = $tree->iterator();

		$root = new QueryNode('__root__');
		$nodes[0] = $root;

		// we need a reference to node! php should do a copy-on-write, we never write
		foreach ($it as $node)
		{
			$id = $node['uuid'];
			$parent = $nodes[$node['parent_uuid']];

			if (preg_match('/.*parents$/', $node['name']))
			{
				$new_node = new QueryNode($node['name'], $node);
			}
			else
			{
				$new_node = new ParseNode($node['name'], $node);
			}

			$nodes[$id] = $new_node;
			$parent->addChild($new_node);
		}






		$all_ids = $entry_ids;



		// This needs to happen in a loop for all query nodes on the tree!

		$root_leave_paths = $this->_subtree_query($root, $entry_ids);

		$unique_ids = $this->_unique_entry_ids($root, $root_leave_paths);
		$all_ids = array_merge($all_ids, $unique_ids);
		$root->entry_ids = $entry_ids;


		$cit = new RecursiveIteratorIterator(
			new ClosureTreeIterator(array($root)),
			RecursiveIteratorIterator::SELF_FIRST
		);


	//	$last_depth = 0;

		foreach ($cit as $node)
		{
			$depth = $cit->getDepth();

			if ($depth == 0 && $node->name == '__root__')
			{
				continue;
			}

			$ids = call_user_func_array('array_merge', $node->parent()->entry_ids);

			// @todo reverse query for parent
			$result_ids = $this->_parenttree_query($node, $ids);
			$result_ids = $this->_unique_entry_ids($node, $result_ids);
			$all_ids = array_merge($all_ids, $result_ids);
		}

		// recurse down to closure ids and rerun the query
		// TODO @pk @todo

		// For space savings and subtree closure querying each need node is
		// pushed its own set of entry ids. For given parent ids.
		//						 {[6, 7]}
		//						/		\	
		//		 {6:[2,4], 7:[8,9]}    	{6:[], 7:[2,5]}
		//				/					\
		//	  		...				  		 ...

		// By pushing them down like this the subtree query is very simple.
		// And when we parse we simply go through all of them and make that
		// many copies of the node's tagdata.

		$it = new RecursiveIteratorIterator(
			new NodeTreeIterator(array($root)),
			RecursiveIteratorIterator::SELF_FIRST
		);

		// add entry ids to the proper tree parse nodes
		// L0 = root
		// L1 = closure-depth=1 querynodes (match field names)
		// ...

		// ready set, main query.
		$EE = get_instance();
		$db = $EE->relationships->_isolate_db();

		$EE->load->model('channel_entries_model');
		$entries_result = $db->query($EE->channel_entries_model->get_entry_sql(array_unique($all_ids)));

		$entry_lookup = array();

		// And then we need to use the lookup table in our data array to
		// populate our mostly empty entries with their data. 
		foreach ($entries_result->result_array() as $entry)
		{
			$entry_lookup[$entry['entry_id']] = $entry;
		}

		// PARSE! FINALLY!

		$var_id_lookup = array();
		$variables = array();

		foreach ($it as $node)
		{
			$depth = $it->getDepth();
			$entry_ids = $node->entry_ids;
			
			if ($depth == 0)
			{
				foreach(array_unique($entry_ids) as $id)
				{
					$variables[$id] = $entry_lookup[$id];
					$var_id_lookup[$id] =& $variables[$id];
				}

				continue;
			}

			if ( ! is_array($entry_ids))
			{
				continue;
			}

			foreach ($entry_ids as $parent => $children)
			{
				$name = $node->name;
				$parent_node =& $var_id_lookup[$parent];

				if ( ! isset($parent_node[$name]))
				{
					$parent_node[$name] = array();
				}

				foreach (array_unique($children) as $child_id)
				{
					$values = $entry_lookup[$child_id];
					$new_values = array();

					foreach ($values as $k => $v)
					{
						$new_values[$name.':'.$k] = $v;
					}

					$l = count($parent_node[$name]);

					$parent_node[$name][$l] = $new_values;
					$var_id_lookup[$child_id] =& $parent_node[$name][$l];
				}

			}
		}

		$this->variables = $variables;
	}


	protected function _unique_entry_ids($node, $leave_paths)
	{

		$it = new RecursiveIteratorIterator(
			new NodeTreeIterator(array($node)),
			RecursiveIteratorIterator::SELF_FIRST
		);

		// add entry ids to the proper tree parse nodes
		// L0 = root
		// L1 = closure-depth=1 querynodes (match field names)
		// ...

		$root_offset = 0;

		$all_ids = array();
		$leaves = $this->_parse_leaves($leave_paths);

		foreach ($it as $node)
		{
			$depth = $it->getDepth();

			if ($depth == 0 && $node->name == '__root__')
			{
				$root_offset = -1;
				continue;
			}


			// the lookup below starts one up from the root @todo fix that!
			$depth += $root_offset;
			$field_id = $node->tag_info['field_id'];

			if ($field_id == 'parents')
			{
				$field_id = $node->tag_info['params']['field_id'];
			}

			if ($field_id != 'siblings')
			{
				$node->entry_ids = array();

				if (isset($leaves[$depth][$field_id])) // @pk @todo, this is the key to removing tags?
				{
					foreach ($leaves[$depth][$field_id] as $parent => $children)
					{
						$node->entry_ids[$parent] = array();

						foreach ($children as $child)
						{
							$all_ids[] = $child['id'];
							$node->entry_ids[$parent][] = $child['id'];
						}
					}
				}
			}
		}

		return $all_ids;
	}


	protected function _parenttree_query($root, $entry_ids)
	{
		// tree branch length extrema
		$depths = $this->_min_max_branches($root);

		$shortest_branch_length = $depths['shortest'];
		$longest_branch_length = $depths['longest'];

		$db = get_instance()->relationships->_isolate_db();

		$db->distinct();
		$db->select('L0.field_id as L0_field');
		$db->select('L0.child_id AS L0_parent');
		$db->select('L0.parent_id as L0_id');
		$db->from('exp_zero_wing as L0');


		for ($level = 0; $level <= $longest_branch_length; $level++)
		{
			$db->join('exp_zero_wing as L' . ($level+1), 
				'L' . ($level) . '.child_id = L' . ($level+1) . '.parent_id' . (($level+1 >= $shortest_branch_length) ? ' OR L' . ($level+1) . '.parent_id = NULL' : ''), 
				($level+1 >= $shortest_branch_length) ? 'left' : '');

			if ($level > 0)
			{
				// Now add the field ID from this level in. We've already done level 0,
				// so just skip it.
				$db->select('L' . $level . '.field_id as L' . $level . '_field');
				$db->select('L' . $level . '.parent_id AS L' . $level . '_parent');
				$db->select('L' . $level . '.child_id as L' . $level . '_id');
			}
		}

		$db->where_in('L0.child_id', $entry_ids);

		return $db->get()->result_array();
	}

	protected function _subtree_query($root, $entry_ids)
	{
		// tree branch length extrema
		// @todo don't count siblings (should be collapsed above)
		$depths = $this->_min_max_branches($root);

		$shortest_branch_length = $depths['shortest'];
		$longest_branch_length = $depths['longest'];

		$db = get_instance()->relationships->_isolate_db();

		$db->distinct();
		$db->select('L0.field_id as L0_field');
		$db->select('L0.parent_id AS L0_parent');
		$db->select('L0.child_id as L0_id');
		$db->from('exp_zero_wing as L0');


		for ($level = 0; $level <= $longest_branch_length; $level++)
		{
			$db->join('exp_zero_wing as L' . ($level+1), 
				'L' . ($level) . '.child_id = L' . ($level+1) . '.parent_id' . (($level+1 >= $shortest_branch_length) ? ' OR L' . ($level+1) . '.parent_id = NULL' : ''), 
				($level+1 >= $shortest_branch_length) ? 'left' : '');

			if ($level > 0)
			{
				// Now add the field ID from this level in. We've already done level 0,
				// so just skip it.
				$db->select('L' . $level . '.field_id as L' . $level . '_field');
				$db->select('L' . $level . '.parent_id AS L' . $level . '_parent');
				$db->select('L' . $level . '.child_id as L' . $level . '_id');
			}
		}

		$db->where_in('L0.parent_id', $entry_ids);

		return $db->get()->result_array();
	}

	// @todo don't count siblings (should be collapsed before we get here)
	protected function _min_max_branches($tree)
	{
		$it = new RecursiveIteratorIterator(
			new ClosureLimitedTreeIterator($tree->children()),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		$shortest = 1E10;
		$longest = 0;

		foreach ($it as $leaf)
		{
			$depth = $it->getDepth();

			if ($depth < $shortest)
			{
				$shortest = $depth;
			}

			if ($depth > $longest)
			{
				$longest = $depth;
			}
		}

		// @todo not the best solution
		if ($shortest > 1E9)
		{
			$shortest = 0;
		}

		return compact('shortest', 'longest');
	}
	
	/**
	 * Parse Paths to Leaves
	 *
	 * Takes the leaf paths data returned by _get_leaves() and turns it into a form
	 * that's more useable by PHP. It breaks each row down into arrays with keys
	 * that we can then use to build a tree.
	 *
	 * @param mixed[] The array of leaves with field and entry_ids and the database returned keys.
	 * @return mixed[] An array with the keys parsed.
	 */
	protected function _parse_leaves(array $leaves)
	{
		$parsed_leaves = array();

		foreach ($leaves as $leaf)
		{
			$i = 0;
			while (isset($leaf['L'.$i.'_field']))
			{
				$field_id = $leaf['L'.$i.'_field'];
				$entry_id = $leaf['L'.$i.'_id'];
				$parent_id = $leaf['L'.$i.'_parent'];

				if ($entry_id == NULL)
				{
					break;
				}

				$field_name = $this->relationship_field_names[$field_id];

				if ( ! isset($parsed_leaves[$i]))
				{
					$parsed_leaves[$i] = array();
				}

				if ( ! isset($parsed_leaves[$i][$field_id]))
				{
					$parsed_leaves[$i][$field_id] = array();
				}

				if ( ! isset($parsed_leaves[$i][$field_id][$parent_id]))
				{
					$parsed_leaves[$i][$field_id][$parent_id] = array();
				}

				$parsed_leaves[$i++][$field_id][$parent_id][] = array(
					'id' => $entry_id,
					'field' => $field_name,
					'parent' => $parent_id
				);
			}
		}

		return $parsed_leaves;
	}

 	// --------------------------------------------------------------------
	
	/**
	 * Take the tagdata from a single entry, and the entry's id
	 * and parse any and all relationship variables in the tag data.
	 * We'll need to have already run the query earlier and have the
	 * data we retrieved from it cached.
	 *
	 * @param	int		The id of the entry we're working with.
	 * @param	string	The tagdata to replace relationship tags in.
	 * 						With all normal entry tags already parsed.
	 *
	 * @return 	string	The parsed tagdata, with all relationship tags
	 *						replaced.
	 */
	public function parse_relationships($entry_id, $tagdata)
	{
		// If we have no relationships, then we can quietly bail out.
		if ($this->variables == NULL)
		{
			return $tagdata;
		}

		$entry_data = $this->variables[$entry_id];
		$tagdata = $this->template->parse_variables_row($tagdata, $entry_data);
		return $tagdata;
	}
}





class TreeNode {
	public $name;
	private $parent;
	private $children = array();

	public function __construct($name)
	{
		$this->name = $name;
	}

	public function addChild(TreeNode $child)
	{
		$child->setParent($this);
		$this->children[] = $child;
	}

	public function children()
	{
		return $this->children;
	}

	public function setParent(TreeNode $parent)
	{
		$this->parent = $parent;
	}

	public function parent()
	{
		return $this->parent;
	}
}

class ParseNode extends TreeNode {

	public $entry_ids;
	public $tag_info;
	private $childTags;				// namespaced tags underneath it that are not relationship tags, constructed from tagdata

	public function __construct($name, array $tag_info = array()) // $tagInfo = array('name' => 'foo', 'params' => array(), 'full_tag' => 'baz')
	{
		parent::__construct($name);
		$this->tag_info = $tag_info;
	}

	public function field_name()
	{
		$field_name = ':'.$this->name;
		return substr($field_name, strrpos($field_name, ':') + 1);
	}
}

/**
 * We store a shortcut path to the kids that need their own queries:
 * http://en.wikipedia.org/wiki/Transitive_closure
 *
 */
class QueryNode extends ParseNode {

	private $closureChildren = array();

	// @override
	public function setParent(TreeNode $p)
	{
		parent::setParent($p);

		do
		{
			if ($p instanceOf QueryNode)
			{
				$p->addClosurePath($this);
				break;
			}

			$p = $p->parent();
		}
		while ($p);
	}

	public function closureChildren()
	{
		return $this->closureChildren;
	}

	public function addClosurePath(QueryNode $closureChild)
	{
		$this->closureChildren[] = $closureChild;
	}
}


class NodeTreeIterator extends RecursiveArrayIterator {

	/**
	 * Override RecursiveArrayIterator's child detection method.
	 * We usually have data rows that are arrays so we really only
	 * want to iterate over those that match our custom format.
	 *
	 * @return boolean
	 */
	public function hasChildren()
	{
		$current = $this->current();
		$children = $current->children();
		return ! empty($children);
	}

	// --------------------------------------------------------------------

	/**
	 * Override RecursiveArrayIterator's get child method to skip
	 * ahead into the __children__ array and not try to iterate
	 * over the data row's individual columns.
	 *
	 * @return Object<TreeIterator>
	 */
	public function getChildren()
	{
		$current = $this->current();
		$children = $current->children();

		// Using ref as per PHP source
		if (empty($this->ref))
		{
			$this->ref = new ReflectionClass($this);
		}

		return $this->ref->newInstance($children);
	}
}

// Does not iterate into query nodes
class ClosureLimitedTreeIterator extends NodeTreeIterator {

	public function hasChildren()
	{
		if ($this->current() instanceOf QueryNode)
		{
			return FALSE;
		}

		return parent::hasChildren();
	}
}

// Iterates only query nodes
class ClosureTreeIterator extends NodeTreeIterator {

	/**
	 * Override RecursiveArrayIterator's child detection method.
	 * We usually have data rows that are arrays so we really only
	 * want to iterate over those that match our custom format.
	 *
	 * @return boolean
	 */
	public function hasChildren()
	{
		$current = $this->current();
		if ( ! $current instanceOF QueryNode)
		{
			return FALSE;
		}

		$children = $current->closureChildren();
		return ! empty($children);
	}

	// --------------------------------------------------------------------

	/**
	 * Override RecursiveArrayIterator's get child method to skip
	 * ahead into the __children__ array and not try to iterate
	 * over the data row's individual columns.
	 *
	 * @return Object<TreeIterator>
	 */
	public function getChildren()
	{
		$current = $this->current();
		$children = $current->closureChildren();

		// Using ref as per PHP source
		if (empty($this->ref))
		{
			$this->ref = new ReflectionClass($this);
		}

		return $this->ref->newInstance($children);
	}
}

/* End of file Relationships.php */
/* Location: ./system/expressionengine/libraries/Relationships.php */