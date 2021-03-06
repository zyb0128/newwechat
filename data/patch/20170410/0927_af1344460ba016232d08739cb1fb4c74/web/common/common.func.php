<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('module');


function url($segment, $params = array()) {
	return wurl($segment, $params);
}


function message($msg, $redirect = '', $type = '') {
	global $_W, $_GPC;
	
	if($redirect == 'refresh') {
		$redirect = $_W['script_name'] . '?' . $_SERVER['QUERY_STRING'];
	}
	if($redirect == 'referer') {
		$redirect = referer();
	}
	if($redirect == '') {
		$type = in_array($type, array('success', 'error', 'info', 'warning', 'ajax', 'sql')) ? $type : 'info';
	} else {
		$type = in_array($type, array('success', 'error', 'info', 'warning', 'ajax', 'sql')) ? $type : 'success';
	}
	if ($_W['isajax'] || !empty($_GET['isajax']) || $type == 'ajax') {
		if($type != 'ajax' && !empty($_GPC['target'])) {
			exit("
<script type=\"text/javascript\">
	var url = ".(!empty($redirect) ? 'parent.location.href' : "''").";
	var modalobj = util.message('".$msg."', '', '".$type."');
	if (url) {
		modalobj.on('hide.bs.modal', function(){\$('.modal').each(function(){if(\$(this).attr('id') != 'modal-message') {\$(this).modal('hide');}});top.location.reload()});
	}
</script>");
		} else {
			$vars = array();
			$vars['message'] = $msg;
			$vars['redirect'] = $redirect;
			$vars['type'] = $type;
			exit(json_encode($vars));
		}
	}
	if (empty($msg) && !empty($redirect)) {
		header('Location: '.$redirect);
		exit;
	}
	$label = $type;
	if($type == 'error') {
		$label = 'danger';
	}
	if($type == 'ajax' || $type == 'sql') {
		$label = 'warning';
	}
	if (is_array($msg)){
		$message_cookie['title'] = 'MYSQL 错误';
		$message_cookie['msg'] = 'php echo cutstr(' . $msg['sql'] . ', 300, 1);';
	} else{
		$message_cookie['title'] = $caption;
		$message_cookie['msg'] = $msg;
	}
	$message_cookie['type'] = $label;
	$message_cookie['redirect'] = $redirect ? $redirect : referer();
	$message_cookie['msg'] = rawurlencode($message_cookie['msg']);
	
	isetcookie('message', stripslashes(json_encode($message_cookie, JSON_UNESCAPED_UNICODE)));
	
	if (!empty($message_cookie['redirect'])) {
		header('Location: ' . $message_cookie['redirect']);
	} else {
		include template('common/message', TEMPLATE_INCLUDEPATH);
	}
	exit;
}


function checklogin() {
	global $_W;
	if (empty($_W['uid'])) {
		if (!empty($_W['setting']['copyright']['showhomepage'])) {
			message('', url('account/welcome'), 'warning');
		} else {
			message('', url('user/login'), 'warning');
		}
	}
	return true;
}


function checkaccount() {
	global $_W;
	if (empty($_W['uniacid'])) {
		message('', url('account/display'), 'info');
	}
}

function buildframes($framename = ''){
	global $_W, $_GPC, $top_nav;
	if (!empty($GLOBALS['frames']) && !empty($_GPC['m'])) {
		$frames = array();
		$globals_frames = (array)$GLOBALS['frames'];
		foreach ($globals_frames as $key => $row) {
			if (empty($row)) continue;
			$row = (array)$row;
			$frames['section']['platform_module_menu'.$key]['title'] = $row['title'];
			foreach ($row['items'] as $li) {
				$frames['section']['platform_module_menu'.$key]['menu']['platform_module_menu'.$li['id']] = array(
					'title' => "<i class='wi wi-appsetting'></i> {$li['title']}",
					'url' => $li['url'],
					'is_display' => 1,
				);
			}
		}
		return $frames;
	}
	$frames = cache_load('system_frame');
	if(empty($frames)) {
		cache_build_frame_menu();
		$frames = cache_load('system_frame');
	}
		$modules = uni_modules(false);
	$sysmodules = system_modules();
	$status = uni_user_permission_exist($_W['uid'], $_W['uniacid']);
		if (!$_W['isfounder'] && $status) {
		$module_permission = pdo_getall('users_permission', array('uid' => $_W['uid'], 'uniacid' => $_W['uniacid'], 'type !=' => 'system'), array('type'));
		if (!empty($module_permission)) {
			foreach ($module_permission as $module) {
				if (!in_array($module['type'], $sysmodules)) {
					$module = $modules[$module['type']];
					if (!empty($module)) {
						$frames['account']['section']['platform_module']['menu']['platform_' . $module['name']] = array(
							'title' => $module['title'],
							'icon' =>  tomedia("addons/{$module['name']}/icon.jpg"),
							'url' => url('home/welcome/ext', array('m' => $module['name'])),
							'is_display' => 1,
						);
						if (file_exists(IA_ROOT. "/addons/{$module['name']}/icon-custom.jpg")) {
							$frames['account']['section']['platform_module']['menu']['platform_' . $module['name']]['icon'] = tomedia("addons/{$module['name']}/icon-custom.jpg");
						}
					}
				}
			}
		} else {
			$frames['account']['section']['platform_module']['is_display'] = false;
		}
	} else {
				$account_module = pdo_getall('uni_account_modules', array('uniacid' => $_W['uniacid'], 'shortcut' => STATUS_ON), array('module'), '', 'displayorder DESC');
		if (!empty($account_module)) {
			foreach ($account_module as $module) {
				if (!in_array($module['module'], $sysmodules)) {
					$module = module_fetch($module['module']);
					if (!empty($module)) {
						$frames['account']['section']['platform_module']['menu']['platform_' . $module['name']] = array(
							'title' => $module['title'],
							'icon' =>  tomedia("addons/{$module['name']}/icon.jpg"),
							'url' => url('home/welcome/ext', array('m' => $module['name'])),
							'is_display' => 1,
						);
						if (file_exists(IA_ROOT. "/addons/{$module['name']}/icon-custom.jpg")) {
							$frames['account']['section']['platform_module']['menu']['platform_' . $module['name']]['icon'] = tomedia("addons/{$module['name']}/icon-custom.jpg");
						}
					}
				}
			}
		} elseif (!empty($modules)) {
			$new_modules = array_reverse($modules);
			$i = 0;
			foreach ($new_modules as $module) {
				if (!empty($module['issystem'])) {
					continue;
				}
				if ($i == 5) {
					break;
				}
				$frames['account']['section']['platform_module']['menu']['platform_' . $module['name']] = array(
					'title' => $module['title'],
					'icon' =>  tomedia("addons/{$module['name']}/icon.jpg"),
					'url' => url('home/welcome/ext', array('m' => $module['name'])),
					'is_display' => 1,
				);
				if (file_exists(IA_ROOT. "/addons/{$module['name']}/icon-custom.jpg")) {
					$frames['account']['section']['platform_module']['menu']['platform_' . $module['name']]['icon'] = tomedia("addons/{$module['name']}/icon-custom.jpg");
				}
				$i++;
			}
		}
		if (array_diff(array_keys($modules), $sysmodules)) {
			$frames['account']['section']['platform_module']['menu']['platform_module_more'] = array(
				'title' => '更多应用',
				'url' => url('profile/module'),
				'is_display' => 1,
			);
		} else {
			$frames['account']['section']['platform_module']['is_display'] = false;
		}
	}
			if (!empty($_W['role']) && ($_W['role'] == ACCOUNT_MANAGE_NAME_OPERATOR || $_W['role'] == ACCOUNT_MANAGE_NAME_MANAGER || $_W['role'] == ACCOUNT_MANAGE_NAME_OWNER)) {
		$user_permission = uni_user_permission('system');
	}
		if (!empty($_W['role']) && $_W['role'] == 'clerk') {
		
	}
		if (!empty($user_permission)) {
		foreach ($frames as $nav_id => $section) {
			if (empty($section['section'])) {
				continue;
			}
			if (in_array("{$nav_id}*", $user_permission)) {
				continue;
			}
			foreach ($section['section'] as $section_id => $secion) {
				$section_show = false;
				foreach ($secion['menu'] as $menu_id => $menu) {
					if (!in_array($menu['permission_name'], $user_permission) && $section_id != 'platform_module') {
						$frames[$nav_id]['section'][$section_id]['menu'][$menu_id]['is_display'] = false;
					} else {
						$section_show = true;
					}
				}
				if (!isset($frames[$nav_id]['section'][$section_id]['is_display'])) {
					$frames[$nav_id]['section'][$section_id]['is_display'] = $section_show;
				}
			}
		}
	}
		$modulename = trim($_GPC['m']);
	$eid = intval($_GPC['eid']);
	if ((!empty($modulename) || !empty($eid)) && !in_array($modulename, system_modules())) {
		if(empty($modulename) && !empty($eid)) {
			$modulename = pdo_getcolumn('modules_bindings', array('eid' => $eid), 'module');
		}
		$module = module_fetch($modulename);
		$entries = module_entries($modulename);

		if($status) {
			$permission = pdo_get('users_permission', array('uniacid' => $_W['uniacid'], 'uid' => $_W['uid'], 'type' => $modulename), array('permission'));
			if(!empty($permission)) {
				$permission = explode('|', $permission['permission']);
			} else {
				$permission = array('account*');
			}
			if($permission[0] != 'all') {
				if(!in_array($modulename.'_rule', $permission)) {
					unset($module['isrulefields']);
				}
				if(!in_array($modulename.'_settings', $permission)) {
					unset($module['settings']);
				}
				if(!in_array($modulename.'_home', $permission)) {
					unset($entries['home']);
				}
				if(!in_array($modulename.'_profile', $permission)) {
					unset($entries['profile']);
				}
				if(!in_array($modulename.'_shortcut', $permission)) {
					unset($entries['shortcut']);
				}
				if(!empty($entries['cover'])) {
					foreach($entries['cover'] as $k => $row) {
						if(!in_array($modulename.'_cover_'.$row['do'], $permission)) {
							unset($entries['cover'][$k]);
						}
					}
				}
				if(!empty($entries['menu'])) {
					foreach($entries['menu'] as $k => $row) {
						if(!in_array($modulename.'_menu_'.$row['do'], $permission)) {
							unset($entries['menu'][$k]);
						}
					}
				}
			}
		}
		$frames['account']['section'] = array();
		if($module['isrulefields'] || !empty($entries['cover']) || !empty($entries['mine'])) {
			if (!empty($module['isrulefields'])) {
				$url = url('platform/reply', array('m' => $modulename));
			}
			if (empty($url) && !empty($entries['cover'])) {
				$url = url('platform/cover', array('eid' => $entries['cover'][0]['eid']));
			}
			$frames['account']['section']['platform_module_common']['menu']['platform_module_entry'] = array(
				'title' => "<i class='wi wi-reply'></i> 应用入口",
				'url' => $url,
				'is_display' => 1,
			);
		}
		if($module['settings']) {
			$frames['account']['section']['platform_module_common']['menu']['platform_module_settings'] = array(
				'title' => "<i class='fa fa-cog'></i> 参数设置",
				'url' => url('profile/module/setting', array('m' => $modulename)),
				'is_display' => 1,
			);
		}
		if($entries['home']) {
			$frames['account']['section']['platform_module_common']['menu']['platform_module_home'] = array(
				'title' => "<i class='fa fa-home'></i> 微站首页导航",
				'url' => url('site/nav/home', array('m' => $modulename)),
				'is_display' => 1,
			);
		}
		if($entries['profile']) {
			$frames['account']['section']['platform_module_common']['menu']['platform_module_profile'] = array(
				'title' => "<i class='fa fa-user'></i> 个人中心导航",
				'url' => url('site/nav/profile', array('m' => $modulename)),
				'is_display' => 1,
			);
		}
		if($entries['shortcut']) {
			$frames['account']['section']['platform_module_common']['menu']['platform_module_shortcut'] = array(
				'title' => "<i class='fa fa-plane'></i> 快捷菜单",
				'url' => url('site/nav/shortcut', array('m' => $modulename)),
				'is_display' => 1,
			);
		}
		if (!empty($entries['cover'])) {
			foreach ($entries['cover'] as $key => $menu) {
				$frames['account']['section']['platform_module_common']['menu']['platform_module_cover'][] = array(
					'title' => "{$menu['title']}",
					'url' => url('platform/cover', array('eid' => $menu['eid'])),
					'is_display' => 0,
				);
			}
		}
		if (!empty($entries['menu'])) {
			$frames['account']['section']['platform_module_menu']['title'] = '业务菜单';
			foreach($entries['menu'] as $key => $row) {
				if(empty($row)) continue;
				foreach($row as $li) {
					$frames['account']['section']['platform_module_menu']['menu']['platform_module_menu'.$row['eid']] = array(
						'title' => "<i class='wi wi-appsetting'></i> {$row['title']}",
						'url' => url('site/entry/', array('eid' => $row['eid'])),
						'is_display' => 1,
					);
				}
			}
		}
	}

	foreach ($frames as $menuid => $menu) {
		if (!empty($menu['founder']) && empty($_W['isfounder'])) {
			continue;
		}
		$top_nav[] = array(
			'title' => $menu['title'],
			'name' => $menuid,
			'url' => $menu['url'],
			'blank' => $menu['blank'],
		);
	}
	return !empty($framename) ? $frames[$framename] : $frames;
}

function system_modules() {
	return array(
		'basic', 'news', 'music', 'userapi', 'recharge', 'images', 'video', 'voice', 'wxcard',
		'custom', 'chats', 'paycenter', 'keyword', 'special', 'welcome', 'default', 'apply', 'reply', 'core'
	);
}


function filter_url($params) {
	global $_W;
	if(empty($params)) {
		return '';
	}
	$query_arr = array();
	$parse = parse_url($_W['siteurl']);
	if(!empty($parse['query'])) {
		$query = $parse['query'];
		parse_str($query, $query_arr);
	}
	$params = explode(',', $params);
	foreach($params as $val) {
		if(!empty($val)) {
			$data = explode(':', $val);
			$query_arr[$data[0]] = trim($data[1]);
		}
	}
	$query_arr['page'] = 1;
	$query = http_build_query($query_arr);
	return './index.php?' . $query;
}

function frames_menu_append() {
	$system_menu_default_permission = array(
		'founder' => array(),
		'owner' => array(
			'system_account',
			'system_module',
			'system_module_group',
			'system_my',
			'system_setting_updatecache',
		),
		'manager' => array(
			'system_account',
			'system_module',
			'system_module_group',
			'system_my',
			'system_setting_updatecache',
		),
		'operator' => array(
			'system_account',
			'system_my',
			'system_setting_updatecache',
		),
		'clerk' => array(),
	);
	return $system_menu_default_permission;
}


function site_profile_perfect_tips(){
	global $_W;
	
	if ($_W['isfounder'] && (empty($_W['setting']['site']) || empty($_W['setting']['site']['profile_perfect']))) {
		if (!defined('SITE_PROFILE_PERFECT_TIPS')) {
			$url = url('cloud/profile');
			return <<<EOF
$(function() {
	var html = 
	    '<div class="we7-body-alert">'+
            '<div class="container">'+
                '<div class="alert alert-info">'+
                    '<i class="wi wi-info-sign"></i>'+
                    '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true" class="wi wi-error-sign"></span><span class="sr-only">Close</span></button>'+
                    '<a href="{$url}" target="_blank">请尽快完善您在微擎云服务平台的站点注册信息。</a>'+
                '</div>'+
            '</div>'+
        '</div>';
	$('body').prepend(html);
});
EOF;
			define('SITE_PROFILE_PERFECT_TIPS', true);
		}
	}
	return '';
}