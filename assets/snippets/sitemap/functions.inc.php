<?php

function getTV($docid, $doctv){
    $output = '';
    while ($pid = evo()->getDocument($docid, 'parent')){
        $tv = evo()->getTemplateVar($doctv,'*',$docid);
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
function getDocs($startid=0, $priority, $changefreq, $excludeTV, $seeThruUnpub){
    $fields = "id,editedon,template,published,searchable,pagetitle,type,isfolder,parent,publishedon";
    $docs = $seeThruUnpub
        ? getAllChildren($startid, $fields)
        : getActiveChildren($startid, $fields);

    // add sub-children to the list
    foreach ($docs as $i => $doc){
        $id = $doc['id'];
        if(!$doc['editedon']) {
            $doc['editedon'] = $doc['publishedon'];
        }

        if($id == evo()->config['site_start']) {
            $docs[$i]['url'] = evo()->config['site_url'];
        } else {
            $docs[$i]['url'] = trim(evo()->makeUrl($id, '', '', 'full'));
        }

        $children = getDocs($id, $priority, $changefreq, $excludeTV, $seeThruUnpub);
        if($children) {
            $_ = array();
            foreach($children as $child) {
                $_[] = $child['editedon'];
            }
            $doc['editedon'] = max($_);
            $docs[$i]['editedon'] = $doc['editedon'];
        }

        $date_diff = round(($_SERVER['REQUEST_TIME']-(int)$doc['editedon'])/86400);

        if(priority_exists($priority))           $docs[$i][$priority] = getTV($id, $priority);
        elseif($id==evo()->config['site_start']) $docs[$i][$priority] = '1.0';
        elseif($date_diff<7)                     $docs[$i][$priority] = $doc['isfolder'] ? '0.7' : '0.9';
        elseif($date_diff<14)                    $docs[$i][$priority] = $doc['isfolder'] ? '0.6' : '0.8';
        elseif($date_diff<30)                    $docs[$i][$priority] = '0.5';
        elseif($date_diff<60)                    $docs[$i][$priority] = '0.4';
        else                                     $docs[$i][$priority] = '0.3';

        if(changefreq_exists($changefreq))       $docs[$i][$changefreq] = getTV($id, $changefreq);
        elseif($id==evo()->config['site_start']) $docs[$i][$changefreq] = 'always';
        elseif($date_diff<7)                     $docs[$i][$changefreq] = 'daily';
        elseif($date_diff<14)                    $docs[$i][$changefreq] = 'weekly';
        elseif($date_diff<60)                    $docs[$i][$changefreq] = 'monthly';
        elseif($date_diff<360)                   $docs[$i][$changefreq] = 'yearly';
        else                                     $docs[$i][$changefreq] = 'never';

        if(excludeTV_exists($excludeTV)) {
            $docs[$i][$excludeTV] = getTV($id, $excludeTV);
        } else {
            $docs[$i][$excludeTV] = false;
        }
        if($children) {
            $docs = array_merge($docs, $children);
        }
    }
    return $docs;
}

function priority_exists($priority_tv) {
    static $exists=null;
    if($exists!==null) {
        return $exists;
    }
    $rs = db()->select(
        'name',
        '[+prefix+]site_tmplvars',
        sprintf("name='%s'",db()->escape($priority_tv))
    );
    $exists = db()->getRecordCount($rs) ? 1 : 0;
    return $exists;
}

function changefreq_exists($changefreq_tv) {
    static $exists=null;
    if($exists!==null) {
        return $exists;
    }
    $rs = db()->select(
        'name',
        '[+prefix+]site_tmplvars',
        sprintf("name='%s'",db()->escape($changefreq_tv))
    );
    $exists = db()->getRecordCount($rs) ? 1 : 0;
    return $exists;
}

function excludeTV_exists($excludeTV) {
    static $exists=null;
    if($exists!==null) {
        return $exists;
    }
    $rs = db()->select(
        'name',
        '[+prefix+]site_tmplvars',
        sprintf("name='%s'",db()->escape($excludeTV))
    );
    $exists = db()->getRecordCount($rs) ? 1 : 0;
    return $exists;
}

function get_site_editedon() {
    $rs = db()->select(
        'MAX(editedon) as max',
        '[+prefix+]site_content',
        'privateweb=0 AND published=1 AND deleted=0',
        '',
        1
    );
    return db()->getValue($rs);
}

function getAllChildren($id= 0, $fields= 'id, pagetitle, description, parent, alias, menutitle') {
    $where = "parent='{$id}' AND deleted=0 AND privateweb=0";
    $rs= db()->select($fields,'[+prefix+]site_content', $where, 'menuindex ASC');
    $docs= array ();
    while ($row = db()->getRow($rs))
    {
        $docs[] = $row;
    }
    return $docs;
}

function getActiveChildren($id= 0, $fields= 'id, pagetitle, description, parent, alias, menutitle') {
    $where = "parent='{$id}' AND published=1 AND deleted=0 AND privateweb=0";
    $rs= db()->select($fields,'[+prefix+]site_content', $where, 'menuindex ASC');
    $docs= array ();
    while ($row = db()->getRow($rs))
    {
        $docs[] = $row;
    }
    return $docs;
}

if(!function_exists('evo')) {
    function evo() {
        global $modx;
        return $modx;
    }
}

if(!function_exists('db')) {
    function db() {
        return evo()->db;
    }
}
