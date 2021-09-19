<?php

function getTV($modx, $docid, $doctv){
    $output = '';
    while ($pid = $modx->getDocument($docid, 'parent')){
        $tv = $modx->getTemplateVar($doctv,'*',$docid);
        if (empty($tv['value']) || strpos($tv['value'], '@INHERIT') !== 0){
            // tv default value is overriden (including empty)
            $output = $tv['value'];
            break;
        }
        // there is no parent with default value overriden
        $output = trim(substr($tv['value'],8));

        // move up one document in document tree
        $docid = $pid['parent'];
    }
    
    return $output;
}

// gets list of published documents with properties
function getDocs($modx, $startid=0, $priority, $changefreq, $excludeTV, $seeThruUnpub){
    $fields = "id,editedon,template,published,searchable,pagetitle,type,isfolder,parent,publishedon,content LIKE '%<img%' as hasImage";
    //If need to see through unpublished
    $docs = $seeThruUnpub ? getAllChildren($startid, $fields) : getActiveChildren($startid, $fields);
    
    $rs = $modx->db->select('name','[+prefix+]site_tmplvars',sprintf("name='%s'",$modx->db->escape($priority)));
    $priority_exists = $modx->db->getRecordCount($rs) ? 1 : 0;
    $rs = $modx->db->select('name','[+prefix+]site_tmplvars',sprintf("name='%s'",$modx->db->escape($changefreq)));
    $changefreq_exists = $modx->db->getRecordCount($rs) ? 1 : 0;
    $rs = $modx->db->select('name','[+prefix+]site_tmplvars',sprintf("name='%s'",$modx->db->escape($excludeTV)));
    $excludeTV_exists  = $modx->db->getRecordCount($rs) ? 1 : 0;
    
    // add sub-children to the list
    foreach ($docs as $i => $doc){
        $id = $doc['id'];
        if(!$doc['editedon']) {
            $doc['editedon'] = $doc['publishedon'];
        }

        if($id == $modx->config['site_start']) {
            $docs[$i]['url'] = $modx->config['site_url'];
        } else {
            $docs[$i]['url'] = trim($modx->makeUrl($id, '', '', 'full'));
        }
        
        $date_diff = round(($_SERVER['REQUEST_TIME']-(int)$doc['editedon'])/86400);
        
        if($priority_exists)                     $docs[$i][$priority] = getTV($modx, $id, $priority); // add priority property
        elseif($id==$modx->config['site_start']) $docs[$i][$priority] = '1.0';
        elseif($date_diff<7)                     $docs[$i][$priority] = '0.9';
        elseif($date_diff<14)                    $docs[$i][$priority] = '0.8';
        elseif($doc['parent']==0)                $docs[$i][$priority] = '0.6';
        elseif($doc['isfolder'])                 $docs[$i][$priority] = '0.4';
        elseif(1000<$date_diff) {
            if($doc['hasImage'])                 $docs[$i][$priority] = '0.4';
            else                                 $docs[$i][$priority] = '0.3';
        }
        else                                     $docs[$i][$priority] = '0.5';
        
        if($changefreq_exists)                   $docs[$i][$changefreq] = getTV($modx, $id, $changefreq); // add changefreq property
        elseif($id==$modx->config['site_start']) $docs[$i][$changefreq] = 'always';
        elseif($doc['isfolder'])                 $docs[$i][$changefreq] = 'always';
        elseif(365<$date_diff)                   $docs[$i][$changefreq] = 'never';
        elseif(180<$date_diff)                   $docs[$i][$changefreq] = 'yearly';
        elseif(60<$date_diff)                    $docs[$i][$changefreq] = 'monthly';
        elseif(14<$date_diff)                    $docs[$i][$changefreq] = 'weekly';
        elseif($date_diff)                       $docs[$i][$changefreq] = 'daily';
        else                                     $docs[$i][$changefreq] = 'never';
        
        if($excludeTV_exists) {
            $docs[$i][$excludeTV] = getTV($modx, $id, $excludeTV);
        } else {
            $docs[$i][$excludeTV] = false;
        }
        
        $children = getDocs($modx, $id, $priority, $changefreq, $excludeTV, $seeThruUnpub);
        if($children) {
            $_ = array();
            foreach($children as $child) {
                $_[] = $child['editedon'];
            }
            $docs[$i]['editedon'] = max($_);
        }
        $docs = array_merge($docs, $children);

    }
    return $docs;
}

function get_site_editedon() {
    global $modx;
    
    $where = 'privateweb=0 AND published=1 AND deleted=0';
    $rs = $modx->db->select('editedon','[+prefix+]site_content', $where, 'editedon DESC', 1);
    return $modx->db->getValue($rs);
}

function getAllChildren($id= 0, $fields= 'id, pagetitle, description, parent, alias, menutitle') {
    global $modx;
    
    $where = "parent='{$id}' AND deleted=0 AND privateweb=0";
    $rs= $modx->db->select($fields,'[+prefix+]site_content', $where, 'menuindex ASC');
    $docs= array ();
    while ($row = $modx->db->getRow($rs))
    {
        $docs[] = $row;
    }
    return $docs;
}

function getActiveChildren($id= 0, $fields= 'id, pagetitle, description, parent, alias, menutitle') {
    global $modx;
    
    $where = "parent='{$id}' AND published=1 AND deleted=0 AND privateweb=0";
    $rs= $modx->db->select($fields,'[+prefix+]site_content', $where, 'menuindex ASC');
    $docs= array ();
    while ($row = $modx->db->getRow($rs))
    {
        $docs[] = $row;
    }
    return $docs;
}
