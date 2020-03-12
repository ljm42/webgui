<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2014-2020, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2020, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// add translations
$_SERVER['REQUEST_URI'] = 'docker';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/webGui/include/Helpers.php";

$DockerClient    = new DockerClient();
$DockerTemplates = new DockerTemplates();
$containers      = $DockerClient->getDockerContainers();
$images          = $DockerClient->getDockerImages();
$user_prefs      = $dockerManPaths['user-prefs'];
$autostart_file  = $dockerManPaths['autostart-file'];

if (!$containers && !$images) {
  echo "<tr><td colspan='7' style='text-align:center;padding-top:12px'>"._('No Docker containers installed')."</td></tr>";
  return;
}

if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($containers as $ct) $sort[] = array_search($ct['Name'],$prefs) ?? 999;
  array_multisort($sort,SORT_NUMERIC,$containers);
  unset($sort);
}

// Read container info
$allInfo = $DockerTemplates->getAllInfo();
$docker = ['var docker=[];'];
$null = '0.0.0.0';
$menu = [];

$autostart = @file($autostart_file, FILE_IGNORE_NEW_LINES) ?: [];
$names = array_map('var_split', $autostart);

function my_lang_log($text) {
  global $language;
  if (isset($language['healthy'])) $text = str_replace('healthy',$language['healthy'],$text);
  if (isset($language['Exited'])) $text = str_replace('Exited',$language['Exited'],$text);
  return my_lang(my_lang(_($text),2));
}

foreach ($containers as $ct) {
  $name = $ct['Name'];
  $id = $ct['Id'];
  $info = &$allInfo[$name];
  $running = $info['running'] ? 1 : 0;
  $paused = $info['paused'] ? 1 : 0;
  $is_autostart = $info['autostart'] ? 'true':'false';
  $updateStatus = substr($ct['NetworkMode'],-4)==':???' ? 2 : ($info['updated']=='true' ? 0 : ($info['updated']=='false' ? 1 : 3));
  $template = $info['template'];
  $shell = $info['shell'];
  $webGui = html_entity_decode($info['url']);
  $support = html_entity_decode($info['Support']);
  $project = html_entity_decode($info['Project']);
  $registry = html_entity_decode($info['registry']);
  $menu[] = sprintf("addDockerContainerContext('%s','%s','%s',%s,%s,%s,%s,'%s','%s','%s','%s','%s','%s');", addslashes($name), addslashes($ct['ImageId']), addslashes($template), $running, $paused, $updateStatus, $is_autostart, addslashes($webGui), $shell, $id, addslashes($support), addslashes($project),addslashes($registry));
  $docker[] = "docker.push({name:'$name',id:'$id',state:$running,pause:$paused,update:$updateStatus});";
  $shape = $running ? ($paused ? 'pause' : 'play') : 'square';
  $status = $running ? ($paused ? 'paused' : 'started') : 'stopped';
  $color = $status=='started' ? 'green-text' : ($status=='paused' ? 'orange-text' : 'red-text');
  $update = $updateStatus==1 ? 'blue-text' : '';
  $icon = $info['icon'] ?: '/plugins/dynamix.docker.manager/images/question.png';
  $image = substr($icon,-4)=='.png' ? "<img src='$icon?".filemtime("$docroot{$info['icon']}")."' class='img'>" : (substr($icon,0,5)=='icon-' ? "<i class='$icon img'></i>" : "<i class='fa fa-$icon img'></i>");
  $wait = var_split($autostart[array_search($name,$names)],1);
  $ports = [];
  foreach ($ct['Ports'] as $port) {
    $intern = $running ? ($ct['NetworkMode']=='host' ? $host : $port['IP']) : $null;
    $extern = $running ? ($port['NAT'] ? $host : $intern) : $null;
    $ports[] = sprintf('%s:%s/%s<i class="fa fa-arrows-h" style="margin:0 6px"></i>%s:%s', $intern, $port['PrivatePort'], strtoupper($port['Type']), $extern, $port['PublicPort']);
  }
  $paths = [];
  $ct['Volumes'] = is_array($ct['Volumes']) ? $ct['Volumes'] : [];
  foreach ($ct['Volumes'] as $mount) {
    list($host_path,$container_path,$access_mode) = explode(':',$mount);
    $paths[] = sprintf('%s<i class="fa fa-%s" style="margin:0 6px"></i>%s', htmlspecialchars($container_path), $access_mode=='ro'?'long-arrow-left':'arrows-h', htmlspecialchars($host_path));
  }
  echo "<tr class='sortable'><td class='ct-name' style='width:220px;padding:8px'>";
  if ($template) {
    $appname = "<a class='exec' onclick=\"editContainer('".addslashes(htmlspecialchars($name))."','".addslashes(htmlspecialchars($template))."')\">".htmlspecialchars($name)."</a>";
  } else {
    $appname = htmlspecialchars($name);
  }
  echo "<span class='outer'><span id='$id' class='hand'>$image</span><span class='inner'><span class='appname $update'>$appname</span><br><i id='load-$id' class='fa fa-$shape $status $color'></i><span class='state'>"._($status)."</span></span></span>";
  echo "<span class='advanced'>"._('Container ID').": $id<br>";
  if ($ct['BaseImage']) echo "<i class='fa fa-cubes' style='margin-right:5px'></i>".htmlspecialchars(${ct['BaseImage']})."<br>";
  echo _('By').": ";
  $registry = $info['registry'];
  list($author,$version) = explode(':',$ct['Image']);
  if ($registry) {
    echo "<a href='".htmlspecialchars($registry)."' target='_blank'>".htmlspecialchars($author)."</a>";
  } else {
    echo htmlspecialchars($author);
  }
  echo "</span></td><td class='updatecolumn'>";
  switch ($updateStatus) {
  case 0:
    echo "<span class='green-text' style='white-space:nowrap;'><i class='fa fa-check fa-fw'></i> "._('up-to-date')."</span>";
    echo "<div class='advanced'><a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download fa-fw'></i> "._('force update')."</span></a></div>";
    break;
  case 1:
    echo "<div class='advanced'><span class='orange-text' style='white-space:nowrap;'><i class='fa fa-flash fa-fw'></i> "._('update ready')."</span></div>";
    echo "<a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download fa-fw'></i> "._('apply update')."</span></a>";
    break;
  case 2:
    echo "<div class='advanced'><span class='orange-text' style='white-space:nowrap;'><i class='fa fa-flash fa-fw'></i> ".('rebuild ready')."</span></div>";
    echo "<a class='exec'><span style='white-space:nowrap;'><i class='fa fa-recycle fa-fw'></i> "._('rebuilding')."</span></a>";
    break;
  default:
    echo "<span class='orange-text' style='white-space:nowrap;'><i class='fa fa-unlink'></i> "._('not available')."</span>";
    echo "<div class='advanced'><a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download fa-fw'></i> "._('force update')."</span></a></div>";
    break;
  }
  echo "<div class='advanced'><i class='fa fa-info-circle fa-fw'></i> $version</div></td>";
  echo "<td>{$ct['NetworkMode']}</td>";
  echo "<td style='white-space:nowrap'><span class='docker_readmore'>".implode('<br>',$ports)."</span></td>";
  echo "<td style='word-break:break-all'><span class='docker_readmore'>".implode('<br>',$paths)."</span></td>";
  echo "<td class='advanced'><span class='cpu-$id'>0%</span><div class='usage-disk mm'><span id='cpu-$id' style='width:0'></span><span></span></div>";
  echo "<br><span class='mem-$id'>0 / 0</span></td>";
  echo "<td><input type='checkbox' id='$id-auto' class='autostart' container='".htmlspecialchars($name)."'".($info['autostart'] ? ' checked':'').">";
  echo "<span id='$id-wait' style='float:right;display:none'>"._('wait')."<input class='wait' container='".htmlspecialchars($name)."' type='number' value='$wait' placeholder='0' title='"._('seconds')."'></span></td>";
  echo "<td><a class='log' onclick=\"containerLogs('".addslashes(htmlspecialchars($name))."','$id',false,false)\"><img class='basic' src='/plugins/dynamix/icons/log.png'><div class='advanced'>";
  echo htmlspecialchars(str_replace('Up',_('Uptime'),my_lang_log($ct['Status'])))."</div><div class='advanced' style='margin-top:4px'>"._('Created')." ".htmlspecialchars(my_lang($ct['Created']))."</div></a></td></tr>";
}
foreach ($images as $image) {
  if (count($image['usedBy'])) continue;
  $id = $image['Id'];
  $menu[] = sprintf("addDockerImageContext('%s','%s');", $id, implode(',',$image['Tags']));
  echo "<tr class='advanced'><td style='width:220px;padding:8px'>";
  echo "<span class='outer apps'><span id='$id' class='hand'><img src='/webGui/images/disk.png' class='img'></span><span class='inner'>("._('orphan image').")<br><i class='fa fa-square stopped grey-text'></i><span class='state'>"._('stopped')."</span></span></span>";
  echo "</td><td colspan='6'>"._('Image ID').": $id<br>";
  echo implode(', ',array_map('htmlspecialchars',$image['Tags']));
  echo "</td><td>"._('Created')." ".htmlspecialchars(my_lang($image['Created']))."</td></tr>";
}
echo "\0".implode($menu).implode($docker)."\0".(pgrep('rc.docker')!==false ? 1:0);
?>
