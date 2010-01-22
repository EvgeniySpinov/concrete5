<?
/**
 * @access private
 * @package Helpers
 * @category Concrete
 * @author Andrew Embler <andrew@concrete5.org>
 * @copyright  Copyright (c) 2003-2008 Concrete5. (http://www.concrete5.org)
 * @license    http://www.concrete5.org/license/     MIT License
 */

/**
 * @access private
 * @package Helpers
 * @category Concrete
 * @author Andrew Embler <andrew@concrete5.org>
 * @copyright  Copyright (c) 2003-2008 Concrete5. (http://www.concrete5.org)
 * @license    http://www.concrete5.org/license/     MIT License
 */
 
defined('C5_EXECUTE') or die(_("Access Denied."));
Loader::model('page_list');
class ConcreteDashboardSitemapHelper {

	// todo: implement aliasing support in getnode
	// getnode needs to check against the session array, to see if it should open a node, and get subnodes
	// integrate droppables
	
	function addOpenNode($cID) {
		if (is_array($_SESSION['dsbSitemapNodes'])) {
			if (in_array($cID, $_SESSION['dsbSitemapNodes'])) {
				return true;
			}
		}
		
		$_SESSION['dsbSitemapNodes'][] = $cID;	
	}
	
	function addOneTimeActiveNode($cID) {
		$_SESSION['dsbSitemapActiveNode'] = $cID;	
	}
	
	function clearOneTimeActiveNodes() {
		unset($_SESSION['dsbSitemapActiveNode']);
	}
	
	function showSystemPages() {
		return $_SESSION['dsbSitemapShowSystem'] == 1;
	}
	
	function isOneTimeActiveNode($cID) {
		return ($_SESSION['dsbSitemapActiveNode'] == $cID);
	}
	
	function getNode($cItem, $level = 0, $autoOpenNodes = true) {
		if (!is_object($cItem)) {
			$cID = $cItem;
			$c = Page::getByID($cID, 'RECENT');
		} else {
			$cID = $cItem->getCollectionID();
			$c = $cItem;
		}
		
		/*
		$db = Loader::db();
		$v = array($cID);
		$q = "select cPointerID from Pages where cID = ?";
		$cPointerID = $db->getOne($q, $v);
		if ($cPointerID > 0) {
			$v = array($cPointerID);	
		} else {
			$cPointerID = 0;
		}

		//$q = "select Pages.cPendingAction, Pages.cChildren, CollectionVersions.cID, CollectionVersions.cvName, PageTypes.ctIcon, CollectionVersions.cvIsApproved from Pages left join PageTypes on Pages.ctID = PageTypes.ctID inner join CollectionVersions on Pages.cID = CollectionVersions.cID where CollectionVersions.cID = ? order by CollectionVersions.cvDateCreated desc limit 1";
		//$r = $db->query($q, $v);
		//$row = $r->fetchRow();
		
		*/
		
		
		$cp = new Permissions($c);
		
		/*
		if ($c->isSystemPage() && (!ConcreteDashboardSitemapHelper::showSystemPages())) {
			return false;
		}
		
		if ((!$cp->canRead()) && ($c->getCollectionPointerExternalLink() == null)) {
			return false;
		}
		*/
		
		$canWrite = ($cp->canWrite()) ? true : false;
		
		$nodeOpen = false;
		if (is_array($_SESSION['dsbSitemapNodes'])) {
			if (in_array($cID, $_SESSION['dsbSitemapNodes'])) {
				$nodeOpen = true;
			}
		}
		
		$status = '';
		
		if ($c->getPendingAction() || ( $c->getVersionObject() && $c->getVersionObject()->isApproved()) ) {
			$status = ucfirst($c->getPendingAction());
		}
		
		$cls = ($c->getNumChildren() > 0) ? "folder" : "file";
		$leaf = ($c->getNumChildren() > 0) ? false : true;
		$numSubpages = ($c->getNumChildren()  > 0) ? $c->getNumChildren()  : '';
		
		$cvName = ($c->getCollectionName()) ? $c->getCollectionName() : '(No Title)';
		$selected = (ConcreteDashboardSitemapHelper::isOneTimeActiveNode($cID)) ? true : false;
		
		$cIcon = $c->getCollectionIcon();
		$cAlias = $c->isAlias();
		if ($cAlias) {
			if ($cPointerID > 0) {
				$cIcon = ASSETS_URL_IMAGES . '/icons/alias.png';
				$cAlias = 'POINTER';
			} else {
				$cIcon = ASSETS_URL_IMAGES . '/icons/alias_external.png';
				$cAlias = 'LINK';
			}
		}
		$node = array(
			'cvName'=> $cvName,
			'cIcon' => $cIcon,
			'cAlias' => $cAlias,
			'numSubpages'=> $numSubpages,
			'status'=> $status,
			'canWrite'=>$canWrite,
			'id'=>$cID,
			'selected'=>$selected
		);
		
		if ($cID == 1 || ($nodeOpen && $autoOpenNodes)) {
			// We open another level
			$node['subnodes'] = ConcreteDashboardSitemapHelper::getSubNodes($cID, $level, false, $autoOpenNodes);
		}
		
		return $node;
	}
	
	function getSubNodes($cID, $level = 0, $keywords = '', $autoOpenNodes = true) {
		$db = Loader::db();
		
		
		if ($keywords != '' && $keywords != false) {
			$nc = Page::getByID($cID, 'RECENT');
			$q1 = $db->quote('%' . $keywords . '%');
			$path = $db->quote($nc->getCollectionPath() . '%');
			
			$q = "select Pages.cID from Pages inner join PagePaths pp on Pages.cID = pp.cID inner join CollectionVersions cv on Pages.cID = cv.cID and cv.cvID = (select max(cvID) from CollectionVersions where cID = Pages.cID) where cPath like $path and (cvName like $q1) and Pages.cID <> $cID";
			$r = $db->query($q);
		} else {			
			$pl = new PageList();
			$pl->sortByDisplayOrder();
			$pl->filterByParentID($cID);
			$pl->displayUnapprovedPages();
			if ($cID == 1) {
				$results = $pl->get();			
			} else {
				$results = $pl->get();			
			}
		}

		foreach($results as $c) {
			$n = ConcreteDashboardSitemapHelper::getNode($c, $level+1, $autoOpenNodes);
			if ($n != false) {
				$nodes[] = $n;
			}
		}
		
		return $nodes;
	}
	
	function getDroppables($cID) {
		$db = Loader::db();
		$v = array($cID);
		$q = "select cID from Pages where cParentID = ? and cPointerID = 0 or cPointerID is null";
		$r = $db->query($q, $v);
		$drops = array();
		while ($row = $r->fetchRow()) {
			$drops[] = $row['cID'];
		}
		return $drops;
	}
	
	function canRead() {
		$sm = Page::getByPath('/dashboard/sitemap');
		$smp = new Permissions($sm);
		return $smp->canRead();
	}


}

?>