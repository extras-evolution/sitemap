<?php
if(!defined('MODX_BASE_PATH'))exit('-');
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');
/**
 * Sitemap
 * 
 * Outputs a machine readable site map for search engines and robots.
 *
 * @category snippet
 * @version 1.1 (2017-08-18)
 * @license LGPL
 * @author Grzegorz Adamiak [grad], ncrossland, DivanDesign (http://www.DivanDesign.biz)
 * @internal @modx_category Navigation
 * 
 * @param startid {integer} - Id of the 'root' document from which the sitemap starts. Default: 0.
 * @param format {string} - Which format of sitemap to use: sp (Sitemap Protocol used by Google), txt (text file with list of URLs), ror (Resource Of Resources). Default: sp.
 * @param seeThruUnpub {0; 1} - See through unpublished documents. Default: 1.
 * @param priority {string} - Name of TV which sets the relative priority of the document. If there is no such TV, this parameter will not be used. Default: 'sitemap_priority'.
 * @param changefreq {string} - Name of TV which sets the change frequency. If there is no such TV this parameter will not be used. Default: 'sitemap_changefreq'.
 * @param excludeTemplates {comma separated string} - Documents based on which templates should not be included in the sitemap. Comma separated list with names of templates. Default: ''.
 * @param excludeTV {string} - Name of TV (boolean type) which sets document exclusion form sitemap. If there is no such TV this parameter will not be used. Default: 'sitemap_exclude'.
 * @param xsl {string; integer} - URL to the XSL style sheet or doc ID of the XSL style sheet. Default: ''.
 * @param excludeWeblinks {0; 1} - Should weblinks be excluded? You may not want to include links to external sites in your sitemap, and Google gives warnings about multiple redirects to pages within your site. Default: 0.
 */
 
 
/*
Supports the following formats:

- Sitemap Protocol used by Google Sitemaps
  (http://www.google.com/webmasters/sitemaps/)

- URL list in text format
  (e.g. Yahoo! submission)

*/

/* Parameters */
if(!isset($startid))          $startid = 0;
if(!isset($priority))         $priority = 'sitemap_priority';
if(!isset($changefreq))       $changefreq = 'sitemap_changefreq';
if(!isset($excludeTemplates)) $excludeTemplates = array();
if(!isset($excludeTV))        $excludeTV = 'sitemap_exclude';
if(!isset($xsl))              $xsl = '';
if(!isset($excludeWeblinks))  $excludeWeblinks = 1;

$seeThruUnpub = (isset($seeThruUnpub) && $seeThruUnpub == '0') ? false : true;
$format       = (isset($format) && ($format != 'ror')) ? $format : 'sp';
if (is_numeric($xsl)) $xsl = $modx->makeUrl($xsl);

/* End parameters */

// get list of documents
$docs = getDocs($modx, $startid, $priority, $changefreq, $excludeTV, $seeThruUnpub);


// filter out documents by template or TV
// ---------------------------------------------
// get all templates
$select = $modx->db->select('id, templatename', '[+prefix+]site_templates');
while ($query = $modx->db->getRow($select)){
    $allTemplates[$query['id']] = $query['templatename'];
}

$remainingTemplates = $allTemplates;

// get templates to exclude, and remove them from the all templates list
if (!empty ($excludeTemplates)){
    
    $excludeTemplates = explode(',', $excludeTemplates);
    
    // Loop through each template we want to exclude
    foreach ($excludeTemplates as $template){
        $template = trim($template);
        
        // If it's numeric, assume it's an ID, and remove directly from the $allTemplates array
        if (is_numeric($template) && isset($remainingTemplates[$template])){
            unset($remainingTemplates[$template]);
        }elseif (trim($template) && in_array($template, $remainingTemplates)){ // If it's text, and not empty, assume it's a template name
            unset($remainingTemplates[array_search($template, $remainingTemplates)]);
        }
    }
}

$_ = array();
// filter out documents which shouldn't be included
foreach ($docs as $doc){
    
    $docid = $doc['id'];
    
    //by template, excludeTV, published, searchable
    if(!isset($remainingTemplates[$doc['template']])) continue;
    if($doc[$excludeTV])                              continue;
    if($doc[$changefreq]=='exclude')                  continue;
    if(!$doc['published'])                            continue;
    if(!$doc['template'])                             continue;
    if(!$doc['searchable'])                           continue;
    if($excludeWeblinks && $doc['type']=='reference') continue;
    if($docid==$modx->documentIdentifier)             continue;
    
    $_[$docid] = $doc;
}
$docs = $_;
unset ($_, $allTemplates, $excludeTemplates);

$site_editedon = get_site_editedon();
if($site_editedon) {
    $docs[$modx->config['site_start']]['editedon'] = $site_editedon;
}

// build sitemap in specified format
// ---------------------------------------------

$output = array();
switch ($format){
    // Next case added in version 1.0.4
    case 'ulli': // UL List
        $output[] = '<ul class="sitemap">';
        // TODO: Sort the array on Menu Index
        // TODO: Make a nested ul-li based on the levels in the document tree.
        foreach ($docs as $doc){
            $s  = '  <li class="sitemap">';
            $s .= '<a href="'.$doc['url'].'" class="sitemap">' . $doc['pagetitle'] . '</a>';
            $s .= '</li>';
            $output[] = $s;
        }
        
        $output[] = '</ul>';
    break;
        
    case 'txt': // plain text list of URLs

        foreach ($docs as $doc){
            $output[] = $doc['url'];
        }
        
    break;

    case 'ror': // TODO
    default: // Sitemap Protocol
        $output[] = '<?xml version="1.0" encoding="'.$modx->config["modx_charset"].'"?>';
        if ($xsl) $output[] ='<?xml-stylesheet type="text/xsl" href="'.$xsl.'"?>';
        
        $output[] ='<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($docs as $doc) {
            $output[] = '    <url>';
            $output[] = '        <loc>'.htmlentities($doc['url']).'</loc>';
            if($doc['editedon'])
                $output[] = '        <lastmod>'.date('Y-m-d', $doc['editedon']).'</lastmod>';
            $output[] = '        <priority>'.$doc[$priority].'</priority>';
            $output[] = '        <changefreq>'.$doc[$changefreq].'</changefreq>';
            $output[] = '    </url>';
        }
        
        $output[] = '</urlset>';

}

return join("\n",$output);

// functions
// ---------------------------------------------

// gets (inherited) value of templat e variable
//TODO: wtf? In MODx 0.9.2.1 O_o Is this actually?
function getTV($modx, $docid, $doctv){
    $output = '';
    while ($pid = $modx->getDocument($docid, 'parent')){
        $tv = $modx->getTemplateVar($doctv,'*',$docid);
        if (($tv['value'] && substr($tv['value'],0,8) != '@INHERIT') || !$tv['value']){ // tv default value is overriden (including empty)
            $output = $tv['value'];
            break;
        }else{ // there is no parent with default value overriden 
            $output = trim(substr($tv['value'],8));
        }
        
        // move up one document in document tree
        $docid = $pid['parent'];
    }
    
    return $output;
}

// gets list of published documents with properties
function getDocs($modx, $startid=0, $priority, $changefreq, $excludeTV, $seeThruUnpub){
    $fields = "id,editedon,template,published,searchable,pagetitle,type,isfolder,parent,publishedon,content LIKE '%<img%' as hasImage";
    //If need to see through unpublished
    if ($seeThruUnpub) $docs = getAllChildren($startid, $fields);
    else               $docs = getActiveChildren($startid, $fields);
    
    $rs = $modx->db->select('name','[+prefix+]site_tmplvars',sprintf("name='%s'",$modx->db->escape($priority)));
    $priority_exists = $modx->db->getRecordCount($rs) ? 1 : 0;
    $rs = $modx->db->select('name','[+prefix+]site_tmplvars',sprintf("name='%s'",$modx->db->escape($changefreq)));
    $changefreq_exists = $modx->db->getRecordCount($rs) ? 1 : 0;
    $rs = $modx->db->select('name','[+prefix+]site_tmplvars',sprintf("name='%s'",$modx->db->escape($excludeTV)));
    $excludeTV_exists  = $modx->db->getRecordCount($rs) ? 1 : 0;
    
    // add sub-children to the list
    foreach ($docs as $i => $doc){
        $id = $doc['id'];
        if(!$doc['editedon']) $doc['editedon'] = $doc['publishedon'];
        if($id==$modx->config['site_start']) $docs[$i]['url'] = $modx->config['site_url'];
        else                                 $docs[$i]['url'] = trim($modx->makeUrl($id,'','','full'));
        
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
        
        if($excludeTV_exists) $docs[$i][$excludeTV] = getTV($modx, $id, $excludeTV); // add excludeTV property
        else                  $docs[$i][$excludeTV] = false;
        
        //TODO: $modx->getAllChildren & $modx->getActiveChildren always return the array
//         if ($modx->getAllChildren($id)){
            $children = getDocs($modx, $id, $priority, $changefreq, $excludeTV, $seeThruUnpub);
            if($children) {
                $_ = array();
                foreach($children as $child) {
                    $_[] = $child['editedon'];
                }
                $docs[$i]['editedon'] = max($_);
            }
            $docs = array_merge($docs, $children);
//         }

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
