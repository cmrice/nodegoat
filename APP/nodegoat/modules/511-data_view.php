<?php

/**
 * nodegoat - web-based data management, network analysis & visualisation environment.
 * Copyright (C) 2019 LAB1100.
 * 
 * nodegoat runs on 1100CC (http://lab1100.com/1100cc).
 *
 * See http://nodegoat.net/release for the latest version of nodegoat and its license.
 */

class data_view extends base_module {

	public static function moduleProperties() {
		static::$label = 'Data View';
		static::$parent_label = 'nodegoat';
	}
	
	public static function modulePreload() {
		
		FormatBBCode::addCode('object_open', '/\[object=([0-9_\|]+)\]/si',
			function ($matches) {
				return '<span class="a popup tag" id="y:data_view:view_type_object-'.$matches[1].'" data-ids="'.$matches[1].'">'; 
			}
		);
		FormatBBCode::addCode('object_close', '/\[\/object\]/si',
			function ($matches) {
				return '</span>'; 
			}
		);

		/*FormatBBCode::addCode('object', '/\[object=([0-9_\|]+)\](.*?)\[\/object\]/si',
			function ($matches) {
				return '<span class="a popup tag" id="y:data_view:view_type_object-'.$matches[1].'" data-ids="'.$matches[1].'">'.$matches[2].'</span>'; 
			}
		);*/
	}
	
	public function contents() {
		
	}
		
	public static function createViewTypeObject($type_id, $object_id) {
		
		$project_id = $_SESSION['custom_projects']['project_id'];
		
		$arr_project = cms_nodegoat_custom_projects::getProjects($project_id);
		$arr_types = StoreType::getTypes(array_keys($arr_project['types']));
		
		$arr_type_set = cms_nodegoat_custom_projects::getTypeSetReferenced($type_id, $arr_project['types'][$type_id], 'view');
		$arr_ref_type_ids = cms_nodegoat_custom_projects::getProjectScopeTypes($project_id);
		
		$arr_analyses_active = data_analysis::getTypeAnalysesActive($type_id);
		
		$arr_selection = ['object' => ['all' => true], 'object_descriptions' => null, 'object_sub_details' => []];
			
		if ($arr_analyses_active) {
			
			$arr_selection['object']['analysis'] = $arr_analyses_active;
		}
				
		$filter = new FilterTypeObjects($type_id, 'all', false, $arr_type_set);			
		$filter->setVersioning('added');
		$filter->setScope(['users' => $_SESSION['USER_ID'], 'types' => $arr_ref_type_ids]);
		$filter->setSelection($arr_selection);
		
		$filter->setFilter(['objects' => $object_id]);
		
		if ($arr_project['types'][$type_id]['type_filter_id']) {
			
			if ($_SESSION['NODEGOAT_CLEARANCE'] < NODEGOAT_CLEARANCE_UNDER_REVIEW) {
				
				$arr_use_project_ids = array_keys($arr_project['use_projects']);
				
				$arr_project_filters = cms_nodegoat_custom_projects::getProjectTypeFilters($project_id, false, false, $arr_project['types'][$type_id]['type_filter_id'], true, $arr_use_project_ids);
				$filter->setFilter(FilterTypeObjects::convertFilterInput($arr_project_filters['object']));
			}
		}
		
		$arr_filters = $filter->getDepth();
		toolbar::setFilter([(int)$type_id => (array)$arr_filters['arr_filters']], true);
		
		$filter->setConditions('style_include', toolbar::getTypeConditions($type_id));
		
		$arr_object = current($filter->init());
		
		if (!$arr_object) {
			return '<section class="info">'.getLabel('msg_not_available').'</section>';
		}
		
		if ($arr_type_set['object_sub_details']) {
			
			$arr_object_subs_info = $filter->getInfoObjectSubs();
			$arr_object_subs_info = $arr_object_subs_info[$object_id];
		}
		
		$arr_source_types = $arr_object['object']['object_sources'];
		$arr_type_object_references = FilterTypeObjects::getTypeObjectReferenced($object_id, $arr_ref_type_ids);
		
		if ($_SESSION['USER_ID']) {
			
			$storage = new StoreTypeObjects($type_id, $object_id, $_SESSION['USER_ID']);
			$arr_version_user = $storage->getTypeObjectVersionsUsers();
				
			if ($arr_version_user[-1]) {
				Labels::setVariable('name', '['.$arr_version_user[-1]['name'].']');
				Labels::setVariable('date', date('d-m-Y', strtotime($arr_version_user[-1]['date'])));
				$str_version = getLabel('inf_added_by_on');
			}
		}
		
		$str_object_id = GenerateTypeObjects::encodeTypeObjectId($type_id, $object_id);
		$str_object_name = $arr_object['object']['object_name'];

		$return = '<div class="tabs view_type_object">
			<ul>
				<li><a href="#">'.getLabel('lbl_overview').'</a></li>
				'.($arr_source_types ? '<li><a href="#">'.getLabel('lbl_sources').'</a></li>' : '').'
				'.($arr_type_object_references ? '<li><a href="#"'.($arr_type_set['type']['is_classification'] ? ' class="open"' : '').'>'.getLabel('lbl_referenced').'</a></li>' : '').'
				'.($_SESSION['NODEGOAT_CLEARANCE'] > NODEGOAT_CLEARANCE_DEMO && $arr_project['types'][$type_id] && $_SESSION['CUR_USER'][DB::getTableName('DEF_NODEGOAT_CUSTOM_PROJECTS')][$project_id]['discussion_provide'] ? '<li><a href="#">'.getLabel('lbl_discussion').'</a></li>' : '').'
			</ul>
			<div class="overview data_viewer">
			
				<h1>'
					.'<span'.($str_version ? ' title="'.$str_version.'"' : '').'>'.$str_object_name.'</span>'
					.($_SESSION['NODEGOAT_CLEARANCE'] > NODEGOAT_CLEARANCE_INTERACT && $arr_project['types'][$type_id] ? '<input type="button" class="data edit popup" id="y:data_entry:edit_quick-'.$type_id.'_'.$object_id.'_view" value="edit" />' : '')
					.'<small title="nodegoat ID">'.$str_object_id.'</small>'
				.'</h1>
				<div class="record"><dl>';
					
					if ($arr_analyses_active) {
						
						$arr_analysis = data_analysis::getTypeAnalysis($type_id);
						$arr_analysis_context = data_analysis::getTypeAnalysisContext($type_id);
				
						$html_analysis = data_analysis::createTypeAnalysisViewValue($type_id, $arr_analysis, $arr_analysis_context, $arr_object['object']['object_analysis']);
						
						$return .= '<li>
							<dt>'.getLabel('lbl_analysis').'</dt>
							<dd>'.$html_analysis.'</dd>
						</li>';
					}
						
					foreach ((array)$arr_type_set['object_descriptions'] as $object_description_id => $arr_object_description) {
						
						$arr_object_definition = $arr_object['object_definitions'][$object_description_id];
						
						if ((!$arr_object_definition['object_definition_value'] && !$arr_object_definition['object_definition_ref_object_id']) || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_description['object_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, $object_description_id) || $arr_object_definition['object_definition_style'] == 'hide') {
							continue;
						}
						
						$arr_object_definition_style = $arr_object_definition['object_definition_style'];
						
						if ($arr_object_description['object_description_ref_type_id']) {
							
							if ($arr_object_description['object_description_is_dynamic']) {
								
								$html_value = '';
								
								foreach ($arr_object_definition['object_definition_ref_object_id'] as $ref_type_id => $arr_ref_objects) {
								
									foreach($arr_ref_objects as $cur_object_id => $arr_reference) {
										
										$html_link = self::createTypeObjectLink($ref_type_id, $cur_object_id, $arr_reference['object_definition_ref_object_name']);
									
										$html_value .= ($arr_object_definition_style ? '<span style="'.$arr_object_definition_style.'">'.$html_link.'</span>' : $html_link).'<span class="icon">'.getIcon('link').'</span>';
									}
								}
							} else if ($arr_object_description['object_description_has_multi']) {
								
								$html_value = '';
								
								foreach ($arr_object_definition['object_definition_ref_object_id'] as $key => $value) {
									
									$html_link = self::createTypeObjectLink($arr_object_description['object_description_ref_type_id'], $value, $arr_object_definition['object_definition_value'][$key]);
									
									$html_value .= ($arr_object_definition_style ? '<span style="'.$arr_object_definition_style.'">'.$html_link.'</span>' : $html_link).'<span class="icon">'.getIcon('link').'</span>';
								}
							} else {
								
								$html_value = self::createTypeObjectLink($arr_object_description['object_description_ref_type_id'], $arr_object_definition['object_definition_ref_object_id'], $arr_object_definition['object_definition_value']);
								$html_value = ($arr_object_definition_style ? '<span style="'.$arr_object_definition_style.'">'.$html_value.'</span>' : $html_value).'<span class="icon">'.getIcon('link').'</span>';
							}
						} else {
							
							$html_value = arrParseRecursive($arr_object_definition['object_definition_value'], ['Labels', 'parseLanguage']);
							
							$html_value = StoreTypeObjects::formatToPresentationValue($arr_object_description['object_description_value_type'], $html_value, $arr_object_description['object_description_value_type_options'], $arr_object_definition['object_definition_ref_object_id']);
							
							if ($arr_object_description['object_description_has_multi']) {
								$html_value = '<div'.($arr_object_definition_style ? ' style="'.$arr_object_definition_style.'"' : '').'>'.$html_value.'</div>';
							} else {
								$html_value = ($arr_object_definition_style ? '<span style="'.$arr_object_definition_style.'">'.$html_value.'</span>' : $html_value);
							}
						}
						
						$str_name = htmlspecialchars(Labels::parseTextVariables($arr_object_description['object_description_name']));
						
						$return .= '<li data-object_description_id="'.$object_description_id.'">
							<dt>'.($arr_object_description['object_description_is_referenced'] ? '<span class="icon" data-category="direction" title="'.getLabel('lbl_referenced').'">'.getIcon('leftright-right').'</span><span>'.Labels::parseTextVariables($arr_types[$arr_object_description['object_description_ref_type_id']]['name']).' - '.$str_name.'</span>' : $str_name).'</dt>
							<dd>'.$html_value.'</dd>
						</li>';
					}
				
				$return .= '</dl></div>';
				
				if ($arr_object_subs_info) {
					
					$arr_object_sub_tabs = ['links' => [], 'content' => []];
					
					foreach ($arr_type_set['object_sub_details'] as $object_sub_details_id => $arr_object_sub_details) {
						
						if (!$arr_object_subs_info[$object_sub_details_id] || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_sub_details['object_sub_details']['object_sub_details_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $object_sub_details_id)) {
							continue;
						}
						
						$arr_object_sub_tabs['links'][] = '<li><a href="#"><span>'.($arr_object_sub_details['object_sub_details']['object_sub_details_type_id'] ?
								'<span class="icon" data-category="direction" title="'.getLabel('lbl_referenced').'">'.getIcon('leftright-right').'</span>'
								.'<span>'.Labels::parseTextVariables($arr_types[$arr_object_sub_details['object_sub_details']['object_sub_details_type_id']]['name']).'</span> '
							: '').
							'<span class="sub-name">'.htmlspecialchars(Labels::parseTextVariables($arr_object_sub_details['object_sub_details']['object_sub_details_name'])).'</span>'
						.'</span></a></li>';
						
						$arr_columns = [];
						$nr_column = 0;
						
						if ($arr_object_sub_details['object_sub_details']['object_sub_details_has_date']) {
							
							$arr_columns[] = '<th class="date" data-sort="asc-0"><span>'.getLabel('lbl_date_start').'</span></th><th class="date"><span>'.getLabel('lbl_date_end').'</span></th>';
							$nr_column += 2;
						}
						if ($arr_object_sub_details['object_sub_details']['object_sub_details_has_location']) {
							
							$arr_columns[] = '<th class="limit disable-sort"></th><th class="max limit disable-sort"><span>'.getLabel('lbl_location').'</span></th>';
							$nr_column += 2;
						}
								
						foreach ($arr_object_sub_details['object_sub_descriptions'] as $object_sub_description_id => $arr_object_sub_description) {
							
							if (!$arr_object_sub_description['object_sub_description_in_overview'] || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_sub_description['object_sub_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $object_sub_details_id, $object_sub_description_id)) {
								continue;
							}
							
							$str_name = Labels::parseTextVariables($arr_object_sub_description['object_sub_description_name']);
									
							$arr_columns[] = '<th class="limit'.($nr_column == 0 ? ' max' : '').($arr_object_sub_description['object_sub_description_value_type'] == 'date' ? ' date' : '').'">'.($arr_object_sub_description['object_sub_description_is_referenced'] ? '<span>'
								.'<span class="icon" data-category="direction" title="'.getLabel('lbl_referenced').'">'.getIcon('leftright-right').'</span>'
								.'<span>'.$str_name.'</span>
							</span>' : '<span>'.$str_name.'</span>').'</th>';
							$nr_column++;
						}
						
						$return_content = '<div>
							'.cms_general::createDataTableHeading('d:data_view:data_object_sub_details-'.$type_id.'_'.$object_id.'_'.$object_sub_details_id.'_1', ['filter' => 'y:data_filter:open_filter-'.$type_id, 'pause' => true, 'search' => false, 'order' => true]).'
								<thead><tr>'
									.implode('', $arr_columns)
								.'</tr></thead>
								<tbody>
									<tr>
										<td colspan="'.($nr_column).'" class="empty">'.getLabel('msg_loading_server_data').'</td>
									</tr>
								</tbody>
							</table>
						</div>';
						
						$arr_object_sub_tabs['content'][] = $return_content;
					}
					
					if (count($arr_object_subs_info) > 1) { // Show combined only if there are multiple subobjects to be shown
						
						array_unshift($arr_object_sub_tabs['links'], '<li><a href="#">'.getLabel('lbl_object_subs').': '.getLabel('lbl_overview').'</a></li>');
						
						$return_content = '<div>
							'.cms_general::createDataTableHeading('d:data_view:data_object_sub_details-'.$type_id.'_'.$object_id.'_all_1', ['filter' => 'y:data_filter:open_filter-'.$type_id, 'pause' => true, 'search' => false, 'order' => true]).'
								<thead><tr>'
									.'<th class="limit" title="'.getLabel('lbl_object_sub').'"><span></span></th>'
									.'<th class="date" data-sort="asc-0"><span>'.getLabel('lbl_date_start').'</span></th><th class="date"><span>'.getLabel('lbl_date_end').'</span></th>'
									.'<th class="limit disable-sort"></th><th class="max limit disable-sort"><span>'.getLabel('lbl_location').'</span></th>'
								.'</tr></thead>
								<tbody>
									<tr>
										<td colspan="5" class="empty">'.getLabel('msg_loading_server_data').'</td>
									</tr>
								</tbody>
							</table>
						</div>';

						array_unshift($arr_object_sub_tabs['content'], $return_content);
					}
					
					$return .= '<div class="tabs object-subs">
						<ul>
							'.($arr_object_sub_tabs ? implode('', $arr_object_sub_tabs['links']) : '').'
						</ul>';
					
						$return .= ($arr_object_sub_tabs ? implode('', $arr_object_sub_tabs['content']) : '');
					
					$return .= '</div>';
				}
				
			$return .= '</div>';
			
			if ($arr_source_types) {
				
				$arr_types_all = StoreType::getTypes(); // Source can be any type
				$arr_html_tabs = [];
				
				$arr_collect_type_object_names = [];
				foreach ((array)$arr_source_types as $ref_type_id => $arr_source_objects) {
					
					$arr_type_object_names = FilterTypeObjects::getTypeObjectNames($ref_type_id, arrValuesRecursive('object_source_ref_object_id', $arr_source_objects), 'style_include');
					
					foreach ($arr_source_objects as $arr_source_object) {
						
						$html = '<p>'.self::createTypeObjectLink($ref_type_id, $arr_source_object['object_source_ref_object_id'], $arr_type_object_names[$arr_source_object['object_source_ref_object_id']]).($arr_source_object['object_source_link'] ? ' - '.$arr_source_object['object_source_link'] : '').'</p>';
						$arr_collect_type_object_names[$ref_type_id]['name'][] = $arr_collect_type_object_names['all']['name'][] = strip_tags($arr_type_object_names[$arr_source_object['object_source_ref_object_id']]);
						$arr_collect_type_object_names[$ref_type_id]['html'][] = $arr_collect_type_object_names['all']['html'][] = $html;
					}
					
					if (!$arr_collect_type_object_names[$ref_type_id]) {
						continue;
					}
					
					array_multisort($arr_collect_type_object_names[$ref_type_id]['name'], SORT_ASC, $arr_collect_type_object_names[$ref_type_id]['html']);
					
					$arr_html_tabs['content'][] = '<div>
						'.implode('', $arr_collect_type_object_names[$ref_type_id]['html']).'
					</div>';
					$arr_html_tabs['links'][] = '<li><a href="#">'.$arr_types_all[$ref_type_id]['name'].'</a></li>';
				}
				if ($arr_collect_type_object_names) {
					
					array_multisort($arr_collect_type_object_names['all']['name'], SORT_ASC, $arr_collect_type_object_names['all']['html']);
					
					array_unshift($arr_html_tabs['links'], '<li><a href="#">'.getLabel('lbl_sources').': '.getLabel('lbl_overview').'</a></li>');
					array_unshift($arr_html_tabs['content'], '<div>
						'.implode('', $arr_collect_type_object_names['all']['html']).'
					</div>');
				}
				
				if ($arr_html_tabs) {
					
					$return .= '<div class="sources">
						<div class="tabs">
							<ul>
								'.implode('', $arr_html_tabs['links']).'
							</ul>
							'.implode('', $arr_html_tabs['content']).'
						</div>
					</div>';
				}
			}
			
			if ($arr_type_object_references) {
				$return .= self::createViewTypeObjectReferenced($type_id, $object_id);
			}
			
			if ($_SESSION['NODEGOAT_CLEARANCE'] > NODEGOAT_CLEARANCE_DEMO && $arr_project['types'][$type_id] && $_SESSION['CUR_USER'][DB::getTableName('DEF_NODEGOAT_CUSTOM_PROJECTS')][$project_id]['discussion_provide']) {
				
				$data_entry = new data_entry();
				
				$return .= '<div class="discussion">
					'.$data_entry->createDiscussion($type_id, $object_id).'
				</div>';
			}
			
		$return .= '</div>';

		return $return;
	}
	
	public static function createTypeObjectSub($type_id, $object_id, $arr_object_sub) {
		
		$project_id = $_SESSION['custom_projects']['project_id'];
		
		$arr_project = cms_nodegoat_custom_projects::getProjects($project_id);
		$arr_types = StoreType::getTypes(array_keys($arr_project['types']));
		
		$arr_type_set = cms_nodegoat_custom_projects::getTypeSetReferenced($type_id, $arr_project['types'][$type_id], 'view');
		
		$object_sub_details_id = $arr_object_sub['object_sub']['object_sub_details_id'];
		$arr_object_sub_details = $arr_type_set['object_sub_details'][$object_sub_details_id];
		$arr_type_object_subs = StoreType::getTypeObjectSubsDetails($arr_object_sub['object_sub']['object_sub_location_ref_type_id']);
		
		$arr_type_object_name = FilterTypeObjects::getTypeObjectNames($type_id, $object_id, 'style_include');
			
		$return .= '<div class="view_type_object object-sub data_viewer">
			<h1>'.$arr_type_object_name[$object_id].'</h1>
			<h2>'.($arr_object_sub_details['object_sub_details']['object_sub_details_type_id'] ? '<span class="icon" data-category="direction" title="'.getLabel('lbl_referenced').'"></span><span>'.Labels::parseTextVariables($arr_types[$arr_object_sub_details['object_sub_details']['object_sub_details_type_id']]['name']).'</span> ' : '').'<span class="sub-name">'.Labels::parseTextVariables($arr_object_sub_details['object_sub_details']['object_sub_details_name']).'</span></h2>
			<div class="record"><dl>';
				
				if ($arr_object_sub_details['object_sub_details']['object_sub_details_has_date']) {
					$return .= '<li>
						<dt>'.getLabel(($arr_object_sub_details['object_sub_details']['object_sub_details_is_date_range'] ? 'lbl_date_start' : 'lbl_date')).'</dt>
						<dd>'.($arr_object_sub['object_sub']['object_sub_date_start'] ? StoreTypeObjects::formatToCleanValue('date', $arr_object_sub['object_sub']['object_sub_date_start']) : '-').'</dd>
					</li>';
					if ($arr_object_sub_details['object_sub_details']['object_sub_details_is_date_range']) {
						$return  .= '<li>
							<dt>'.getLabel('lbl_date_end').'</dt>
							<dd>'.($arr_object_sub['object_sub']['object_sub_date_end'] ? StoreTypeObjects::formatToCleanValue('date', $arr_object_sub['object_sub']['object_sub_date_end']) : '-').'</dd>
						</li>';
					}
				}
				if ($arr_object_sub_details['object_sub_details']['object_sub_details_has_location']) {
					if ($arr_object_sub['object_sub']['object_sub_location_ref_type_id']) {
						$return .= '<li>
							<dt>'.getLabel('lbl_location_reference').'</dt>
							<dd>';
								if ($arr_object_sub['object_sub']['object_sub_location_ref_object_id']) {
									$return .= self::createTypeObjectLink($arr_object_sub['object_sub']['object_sub_location_ref_type_id'], $arr_object_sub['object_sub']['object_sub_location_ref_object_id'], $arr_object_sub['object_sub']['object_sub_location_ref_object_name']);
								} else {
									$return .= '<span>-</span>';
								}
								$return .= '<span class="icon">'.getIcon('link').'</span> <span>('.htmlspecialchars(Labels::parseTextVariables($arr_types[$arr_object_sub['object_sub']['object_sub_location_ref_type_id']]['name'])).'</span> <span class="sub-name">'.htmlspecialchars(Labels::parseTextVariables($arr_type_object_subs[$arr_object_sub['object_sub']['object_sub_location_ref_object_sub_details_id']]['object_sub_details_name'])).'</span>)
							</dd>
						</li>';
					}
					$return .= '<li>
						<dt>'.getLabel('lbl_geometry').'</dt>
						<dd>'.($arr_object_sub['object_sub']['object_sub_location_geometry'] ? StoreTypeObjects::formatToCleanValue('geometry', $arr_object_sub['object_sub']['object_sub_location_geometry']) : '-').'</dd>
					</li>';
				}
										
				if ($arr_object_sub_details['object_sub_descriptions']) {
					foreach ($arr_object_sub_details['object_sub_descriptions'] as $arr_object_sub_description) {
						
						$object_sub_description_id = $arr_object_sub_description['object_sub_description_id'];
						$arr_object_sub_definition = $arr_object_sub['object_sub_definitions'][$object_sub_description_id];

						if ((!$arr_object_sub_definition['object_sub_definition_value'] && !$arr_object_sub_definition['object_sub_definition_ref_object_id']) || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_sub_description['object_sub_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $object_sub_details_id, $object_sub_description_id) || $arr_object_sub_definition['object_sub_definition_style'] == 'hide') {
							continue;
						}
						
						$arr_object_sub_definition_style = $arr_object_sub_definition['object_sub_definition_style'];
						
						if ($arr_object_sub_description['object_sub_description_ref_type_id']) {
							
							if ($arr_object_sub_description['object_sub_description_is_dynamic']) {
							
								$html_value = '';
								
								foreach ($arr_object_sub_definition['object_sub_definition_ref_object_id'] as $ref_type_id => $arr_ref_objects) {
								
									foreach($arr_ref_objects as $cur_object_id => $arr_reference) {
										
										$html_link = self::createTypeObjectLink($ref_type_id, $cur_object_id, $arr_reference['object_sub_definition_ref_object_name']);
									
										$html_value .= ($arr_object_sub_definition_style ? '<span style="'.$arr_object_sub_definition_style.'">'.$html_link.'</span>' : $html_link).'<span class="icon">'.getIcon('link').'</span>';
									}
								}
							} else {
							
								$html_value = self::createTypeObjectLink($arr_object_sub_description['object_sub_description_ref_type_id'], $arr_object_sub_definition['object_sub_definition_ref_object_id'], $arr_object_sub_definition['object_sub_definition_value']);
								$html_value = ($arr_object_sub_definition_style ? '<span style="'.$arr_object_sub_definition_style.'">'.$html_value.'</span>' : $html_value).'<span class="icon">'.getIcon('link').'</span>';
							}
						} else {
							
							$str_value = arrParseRecursive($arr_object_sub_definition['object_sub_definition_value'], ['Labels', 'parseLanguage']);
							
							$html_value = StoreTypeObjects::formatToPresentationValue($arr_object_sub_description['object_sub_description_value_type'], $str_value, $arr_object_sub_description['object_sub_description_value_type_options'], $arr_object_sub_definition['object_sub_definition_ref_object_id']);
							$html_value = ($arr_object_sub_definition_style ? '<span style="'.$arr_object_sub_definition_style.'">'.$html_value.'</span>' : $html_value);
						}
						
						$str_name = htmlspecialchars(Labels::parseTextVariables($arr_object_sub_description['object_sub_description_name']));
						
						$return .= '<li data-object_sub_description_id="'.$object_sub_description_id.'">
								<dt>'.($arr_object_sub_description['object_sub_description_is_referenced'] ?
									'<span class="icon" data-category="direction" title="'.getLabel('lbl_referenced').'">'.getIcon('leftright-right').'</span>'
									.'<span>'.Labels::parseTextVariables($arr_types[$arr_object_sub_description['object_sub_description_ref_type_id']]['name']).' - '.$str_name.'</span>'
								: $str_name).'</dt>
								<dd>'.$html_value.'</dd>
						</li>';
					}
				}
			$return .= '</dl></div>
		</div>';
		
		return $return;
	}
	
	public static function formatToTypeObjectSubValues($type_id, $object_sub_details_id, $object_id = 0, $arr_object_sub = []) {
		
		$arr_project = cms_nodegoat_custom_projects::getProjects($_SESSION['custom_projects']['project_id']);
		$arr_types = StoreType::getTypes(array_keys($arr_project['types']));
		
		$arr_type_set = cms_nodegoat_custom_projects::getTypeSetReferenced($type_id, $arr_project['types'][$type_id], 'view');
		$arr_object_sub_details = $arr_type_set['object_sub_details'][$object_sub_details_id];
		$arr_object_sub_value = $arr_object_sub['object_sub'];
		
		$arr = [];
		
		if ($arr_object_sub_details['object_sub_details']['object_sub_details_has_date']) {
				
			$arr['object_sub_date_start'] = (StoreTypeObjects::formatToCleanValue('date', $arr_object_sub_value['object_sub_date_start']) ?: '-');
			if ($arr_object_sub_value['object_sub_date_start'] != $arr_object_sub_value['object_sub_date_end']) {
				$arr['object_sub_date_end'] = ($arr_object_sub_value['object_sub_date_end'] == DATE_INT_MAX ? '∞' : StoreTypeObjects::formatToCleanValue('date', $arr_object_sub_value['object_sub_date_end']));
			} else {
				$arr['object_sub_date_end'] = '-';
			}
		} else {
			
			$arr['object_sub_date_start'] = '';
			$arr['object_sub_date_end'] = '';
		}
		
		if ($arr_object_sub_details['object_sub_details']['object_sub_details_has_location']) {
				
			$arr['object_sub_location_reference_label'] = '';
			$arr['object_sub_location_reference_value'] = '-';

			if ($arr_object_sub_value['object_sub_location_type'] == 'reference') {
							
				if ($arr_object_sub_value['object_sub_location_ref_type_id']) {
					
					$arr_type_object_subs = StoreType::getTypeObjectSubsDetails($arr_object_sub_value['object_sub_location_ref_type_id']);
					$arr['object_sub_location_reference_label'] = '<span>'.Labels::parseTextVariables($arr_types[$arr_object_sub_value['object_sub_location_ref_type_id']]['name']).'</span>'.($arr_object_sub_value['object_sub_location_ref_object_sub_details_id'] ? ' <span class="sub-name">'.Labels::parseTextVariables($arr_type_object_subs[$arr_object_sub_value['object_sub_location_ref_object_sub_details_id']]['object_sub_details_name']).'</span>' : '');
				
					if ($arr_object_sub_value['object_sub_location_ref_object_id']) {
						$arr['object_sub_location_reference_value'] = data_view::createTypeObjectLink($arr_object_sub_value['object_sub_location_ref_type_id'], $arr_object_sub_value['object_sub_location_ref_object_id'], $arr_object_sub_value['object_sub_location_ref_object_name']);
					}
				}
			} else {
				
				if ($arr_object_sub_value['object_sub_location_geometry']) {
					$arr['object_sub_location_reference_label'] = getLabel('lbl_geometry');
					$arr['object_sub_location_reference_value'] = StoreTypeObjects::formatToCleanValue('geometry', $arr_object_sub_value['object_sub_location_geometry']);
				}
			}
		} else {
			
			$arr['object_sub_location_reference_label'] = '';
			$arr['object_sub_location_reference_value'] = '';
		}
				
		foreach ($arr_object_sub_details['object_sub_descriptions'] as $object_sub_description_id => $arr_object_sub_description) {
			
			if (!isset($arr_object_sub['object_sub_definitions'][$object_sub_description_id])) {
				continue;
			}
			
			$arr_object_sub_definition = $arr_object_sub['object_sub_definitions'][$object_sub_description_id];
			
			if ($arr_object_sub_definition['object_sub_definition_style'] == 'hide') {
		
				$arr['object_sub_definition_'.$object_sub_description_id] = '';
				continue;
			}

			if ($arr_object_sub_description['object_sub_description_ref_type_id']) {
				
				$return = data_view::createTypeObjectLink($arr_object_sub_description['object_sub_description_ref_type_id'], $arr_object_sub_definition['object_sub_definition_ref_object_id'], $arr_object_sub_definition['object_sub_definition_value']);
			} else {
				
				$str_value = arrParseRecursive($arr_object_sub_definition['object_sub_definition_value'], ['Labels', 'parseLanguage']);
				
				$return = StoreTypeObjects::formatToPreviewValue($arr_object_sub_description['object_sub_description_value_type'], $str_value, $arr_object_sub_description['object_sub_description_value_type_options'], $arr_object_sub_definition['object_sub_definition_ref_object_id']);
			}
			
			$arr['object_sub_definition_'.$object_sub_description_id] = $return;
		}

		return $arr;
	}
	
	public static function createTypeObjectLink($type_id, $object_id, $value) {
		
		if (!$object_id) {
			return '';
		}
		
		$return = '<span class="a popup" id="y:data_view:view_type_object-'.$type_id.'_'.$object_id.'">'.$value.'</span>';
		
		return $return;
	}
	
	public static function createViewTypeObjectReferenced($type_id, $object_id) {
		
		$arr_ref_type_ids = cms_nodegoat_custom_projects::getProjectScopeTypes($_SESSION['custom_projects']['project_id']);
		$arr_type_object_references = FilterTypeObjects::getTypeObjectReferenced($object_id, $arr_ref_type_ids);

		if ($arr_type_object_references) {
			
			$return .= '<div class="referenced">';
				
				$arr_dynamic_tags_reference_types_object_descriptions = [];
				
				foreach ($arr_type_object_references as $reference_type_id => $arr_object_reference) {
					
					$arr_reference_type_set = StoreType::getTypeSet($reference_type_id);
					
					$return_statistics .= '<h1>'.Labels::parseTextVariables($arr_reference_type_set['type']['name']).'</h1>';
					
					if ($arr_object_reference['object_definitions'] || $arr_object_reference['object']['object_sources']) {
						
						$return_statistics .= '<div class="record"><dl>';
						
						if ($arr_object_reference['object']['object_sources']) {
							$return_statistics .= '<li><dt><span class="dynamic-references-name">'.getLabel('lbl_source').'</span></dt><dd>'.$arr_object_reference['object']['object_sources']['count'].'</dd></li>';
						}
						
						foreach ((array)$arr_object_reference['object_definitions'] as $object_description_id => $arr_reference) {
							
							$arr_object_description = $arr_reference_type_set['object_descriptions'][$object_description_id];
							
							if (!$arr_object_description) {
								continue;
							}
							
							$html_name = '<span'.($arr_object_description['object_description_is_dynamic'] ? ' class="dynamic-references-name"' : '').'>'.Labels::parseTextVariables($arr_object_description['object_description_name']).'</span>';
							
							if ($arr_reference['count']) {
								$return_statistics .= '<li><dt>'.$html_name.'</dt><dd>'.$arr_reference['count'].'</dd></li>';
							}
							if ($arr_reference['object_definition_sources']['count']) {
								$return_statistics .= '<li><dt>'.$html_name.' <span class="dynamic-references-name">'.getLabel('lbl_source').'</span></dt><dd>'.$arr_reference['object_definition_sources']['count'].'</dd></li>';
							}
							if ($arr_object_description['object_description_is_dynamic'] && !$arr_object_description['object_description_ref_type_id']) {
								$arr_dynamic_tags_reference_types_object_descriptions[$reference_type_id][$object_description_id] = $object_description_id;							
							}
						}
						
						$return_statistics .= '</dl></div>';
					}
					
					if ($arr_object_reference['object_subs']) {
						
						$return_statistics .= '<ul>';
						
						foreach ($arr_object_reference['object_subs'] as $object_sub_details_id => $arr_object_sub) {
							
							$arr_object_sub_details = $arr_reference_type_set['object_sub_details'][$object_sub_details_id];
							
							if (!$arr_object_sub_details) {
								continue;
							}
							
							$return_statistics .= '<li>
								<h2><span class="sub-name">'.Labels::parseTextVariables($arr_object_sub_details['object_sub_details']['object_sub_details_name']).'</span></h2>
								<div class="record"><dl>';
								
								if ($arr_object_sub['object_sub']['object_sub_sources']['count']) {
									$return_statistics .= '<li><dt><span class="dynamic-references-name">'.getLabel('lbl_source').'</span></dt><dd>'.$arr_object_sub['object_sub']['object_sub_sources']['count'].'</dd></li>';
								}
								if ($arr_object_sub['object_sub']['object_sub_location']['count']) {
									$return_statistics .= '<li><dt><span class="dynamic-references-name">'.getLabel('lbl_location').'</span></dt><dd>'.$arr_object_sub['object_sub']['object_sub_location']['count'].'</dd></li>';
								}
								
								foreach ((array)$arr_object_sub['object_sub_definitions'] as $object_sub_description_id => $arr_reference) {

									$arr_object_sub_description = $arr_object_sub_details['object_sub_descriptions'][$object_sub_description_id];
									
									if (!$arr_object_sub_description) {
										continue;
									}
							
									$html_name = '<span'.($arr_object_sub_description['object_sub_description_is_dynamic'] ? ' class="dynamic-references-name"' : '').'>'.Labels::parseTextVariables($arr_object_sub_description['object_sub_description_name']).'</span>';
								
									if ($arr_reference['count']) {
										$return_statistics .= '<li><dt>'.$html_name.'</dt><dd>'.$arr_reference['count'].'</dd></li>';
									}
									if ($arr_reference['object_sub_definition_sources']['count']) {
										$return_statistics .= '<li><dt>'.$html_name.' <span class="dynamic-references-name">'.getLabel('lbl_source').'</span></dt><dd>'.$arr_reference['object_sub_definition_sources']['count'].'</dd></li>';
									}
								}
								
								$return_statistics .= '</dl></div>
							</li>';
						}
						$return_statistics .= '</ul>';
					}

					$arr_html_tabs['links'][$reference_type_id] = '<li><a'.(!$arr_html_tabs['links'] ? ' class="open"' : '').' href="#">'.Labels::parseTextVariables($arr_reference_type_set['type']['name']).'</a></li>';
					
					$return_tab = '<div id="tab-reference-'.$reference_type_id.'">
						<form class="options fieldsets"><div>';
						
						if ($arr_object_reference['object']['object_sources']) {
							
							$return_tab .= '<fieldset><legend><span class="dynamic-references-name">'.getLabel('lbl_source').'</span></legend><ul>
								<li>'.Labels::parseTextVariables(cms_general::createSelectorList([['name' => '<span>'.$arr_reference_type_set['type']['name'].'</span>', 'id' => $object_id]], 'object_sources', 'all')).'</li>
							</ul></fieldset>';
						}
						
						if ($arr_object_reference['object_definitions']) {
							
							$arr_select = [];
							
							foreach ($arr_object_reference['object_definitions'] as $object_description_id => $arr_reference) {
								
								$arr_object_description = $arr_reference_type_set['object_descriptions'][$object_description_id];
								
								if (!$arr_object_description) {
									continue;
								}

								if ($arr_reference['count']) {
									
									$html_name = '<span'.($arr_object_description['object_description_is_dynamic'] ? ' class="dynamic-references-name"' : '').'>'.$arr_object_description['object_description_name'].'</span>';
									
									$arr_select['self'][] = ['id' => $object_description_id, 'name' => $html_name];
								}
								if ($arr_reference['object_definition_sources']['count']) {
									
									$html_name = '<span>'.$arr_object_description['object_description_name'].'</span>';
									
									$arr_select['sources'][] = ['id' => $object_description_id, 'name' => $html_name];
								}
							}
							
							if ($arr_select['self']) {
								
								$return_tab .= '<fieldset><legend><span>'.getLabel('lbl_description').'</span></legend><ul>
									<li>'.Labels::parseTextVariables(cms_general::createSelectorList($arr_select['self'], 'object_descriptions', 'all')).'</li>
								</ul></fieldset>';
							}
							if ($arr_select['sources']) {
								
								$return_tab .= '<fieldset><legend><span>'.getLabel('lbl_description').'</span> <span class="dynamic-references-name">'.getLabel('lbl_source').'</span></legend><ul>
									<li>'.Labels::parseTextVariables(cms_general::createSelectorList($arr_select['sources'], 'object_description_sources', 'all')).'</li>
								</ul></fieldset>';
							}
						}
						if ($arr_object_reference['object_subs']) {
							
							$arr_select = [];
							
							foreach ($arr_object_reference['object_subs'] as $object_sub_details_id => $arr_object_sub) {
								
								$arr_object_sub_details = $arr_reference_type_set['object_sub_details'][$object_sub_details_id];
								
								if (!$arr_object_sub_details) {
									continue;
								}
								
								$html_name_object_sub_details = '<span class="sub-name">'.$arr_object_sub_details['object_sub_details']['object_sub_details_name'].'</span>';
								
								if ($arr_object_sub['object_sub']['object_sub_location']['count']) {
									
									$arr_select['locations'][] = ['id' => $object_sub_details_id, 'name' => $html_name_object_sub_details];
								}
								if ($arr_object_sub['object_sub']['object_sub_sources']['count']) {
									
									$arr_select['sources'][] = ['id' => $object_sub_details_id, 'name' => $html_name_object_sub_details];
								}
								
								foreach ((array)$arr_object_sub['object_sub_definitions'] as $object_sub_description_id => $arr_reference) {
									
									$arr_object_sub_description = $arr_object_sub_details['object_sub_descriptions'][$object_sub_description_id];
									
									if (!$arr_object_sub_description) {
										continue;
									}

									if ($arr_reference['count']) {
										
										$html_name = $html_name_object_sub_details.' <span'.($arr_object_sub_description['object_sub_description_is_dynamic'] ? ' class="dynamic-references-name"' : '').'>'.$arr_object_sub_description['object_sub_description_name'].'</span>';
										
										$arr_select['descriptions']['self'][] = ['id' => $object_sub_description_id, 'name' => '<span>'.$html_name.'</span>'];
									}
									if ($arr_reference['object_sub_definition_sources']['count']) {
										
										$html_name = $html_name_object_sub_details.' <span>'.$arr_object_sub_description['object_sub_description_name'].'</span>';
										
										$arr_select['descriptions']['sources'][] = ['id' => $object_sub_description_id, 'name' => '<span>'.$html_name.'</span>'];
									}
								}
							}
							
							if ($arr_select['locations']) {
								
								$return_tab .= '<fieldset><legend><span>'.getLabel('lbl_object_sub').'</span> <span class="dynamic-references-name">'.getLabel('lbl_location').'</span></legend><ul>
									<li>'.Labels::parseTextVariables(cms_general::createSelectorList($arr_select['locations'], 'object_sub_locations', 'all')).'</li>
								</ul></fieldset>';
							}
							if ($arr_select['sources']) {
								
								$return_tab .= '<fieldset><legend><span>'.getLabel('lbl_object_sub').'</span> <span class="dynamic-references-name">'.getLabel('lbl_source').'</span></legend><ul>
									<li>'.Labels::parseTextVariables(cms_general::createSelectorList($arr_select['sources'], 'object_sub_sources', 'all')).'</li>
								</ul></fieldset>';
							}
							if ($arr_select['descriptions']['self']) {
								
								$return_tab .= '<fieldset><legend><span>'.getLabel('lbl_object_sub').' '.getLabel('lbl_description').'</span></legend><ul>
									<li>'.Labels::parseTextVariables(cms_general::createSelectorList($arr_select['descriptions']['self'], 'object_sub_descriptions', 'all')).'</li>
								</ul></fieldset>';
							}
							if ($arr_select['descriptions']['sources']) {
								
								$return_tab .= '<fieldset><legend><span>'.getLabel('lbl_object_sub').' '.getLabel('lbl_description').'</span> <span class="dynamic-references-name">'.getLabel('lbl_source').'</span></legend><ul>
									<li>'.Labels::parseTextVariables(cms_general::createSelectorList($arr_select['descriptions']['sources'], 'object_sub_description_sources', 'all')).'</li>
								</ul></fieldset>';
							}
						}
						$return_tab .= '</div></form>
						'.self::createViewTypeObjects($reference_type_id, ['referenced_type_id' => (int)$type_id, 'referenced_object_id' => (int)$object_id, 'filter' => $_SESSION['USER_ID'], 'project_filter' => true], true).'
					</div>';
					$arr_html_tabs['content'][$reference_type_id] = $return_tab;
				}
				
				if ($arr_dynamic_tags_reference_types_object_descriptions) {
					
					$arr_html_dynamic_tabs = [];
					
					foreach ($arr_dynamic_tags_reference_types_object_descriptions as $reference_type_id => $arr_object_descriptions) {
						
						$arr_reference_type_set = StoreType::getTypeSet($reference_type_id);
						
						$arr_html_dynamic_tabs['links'][$reference_type_id] = '<li><a'.(!$arr_html_tabs['links'] ? ' class="open"' : '').' href="#">'.Labels::parseTextVariables($arr_reference_type_set['type']['name']).'</a></li>';
						$arr_html_dynamic_tabs['content'][$reference_type_id] = '<div>'.self::createViewTypeObjects($reference_type_id, ['referenced_type_id' => (int)$type_id, 'referenced_object_id' => (int)$object_id, 'dynamic_object_descriptions' => $arr_object_descriptions, 'filter' => $_SESSION['USER_ID'], 'project_filter' => true], true).'</div>';				
					}
					
					$return_dynamic = '<div class="tabs">
						<ul>
							'.implode('', $arr_html_dynamic_tabs['links']).'
						</ul>
						'.implode('', $arr_html_dynamic_tabs['content']).'
					</div>';
				}
				
				$return .= '<div class="tabs">
					<ul>
						<li><a href="#">'.getLabel('lbl_referenced').': '.getLabel('lbl_overview').'</a></li>
						'.($return_dynamic ? '<li><a href="#">'.getLabel('lbl_referenced').': '.getLabel('lbl_tags').'</a></li>' : '' )
						.implode('', $arr_html_tabs['links']).'
					</ul>
					<div class="statistics data_viewer">'.$return_statistics.'</div>
					'.($return_dynamic ? '<div class="dynamic-object-descriptions">'.$return_dynamic.'</div>' : '' )
					.implode('', $arr_html_tabs['content']).'
				</div>';
			
			$return .= '</div>';
		}

		return $return;
	}
	
	public static function createViewTypeObjects($type_id, $arr_options = [], $pause = false) {

		// $arr_options = referenced_object_id/select/filter

		$option = 'load';
		if ($arr_options['session']) {
			$option = 'session';
		}
		if ($arr_options['referenced_object_id'] || $arr_options['dynamic_object_descriptions']) {
			
			$option = '';
			
			if ($arr_options['referenced_type_id']) {
				$option = 'referenced0type|'.(int)$arr_options['referenced_type_id'];
			}
			if ($arr_options['referenced_object_id']) {
				$option .= ($option ? '|' : '').'referenced0object|'.(int)$arr_options['referenced_object_id'];
			}
			
			if ($arr_options['dynamic_object_descriptions']) {
				$arr_options['dynamic_object_descriptions'] = (array)$arr_options['dynamic_object_descriptions'];
				$option .= ($option ? '|' : '').'dynamic|'.implode('+', $arr_options['dynamic_object_descriptions']);
			}
		}
		if ($arr_options['select']) {
			$setting = 'select';
		}
		if ($arr_options['filter']) {
			$filter = true;
		}
		
		$arr_project = cms_nodegoat_custom_projects::getProjects($_SESSION['custom_projects']['project_id']);
		$arr_type_set = StoreType::getTypeSet($type_id);
		
		$filter_reset = new FilterTypeObjects($type_id);
		$filter_reset->resetResultInfo();
		
		$return = cms_general::createDataTableHeading('d:data_view:data-'.$type_id.'_'.$option.'_'.$setting.'_'.($arr_options['project_filter'] ? '1' : '0'), ['filter' => ($filter ? 'y:data_filter:open_filter-'.$type_id : false), 'pause' => $pause, 'order' => true]).'
			<thead><tr>';
			
				if ($setting == 'select') {
					$return .= '<th class="disable-sort"></th>';
				}
				if ($arr_type_set['type']['object_name_in_overview']) {
					$return .= '<th class="max limit"><span>'.getLabel('lbl_name').'</span></th>';
				}
				
				if ($arr_options['dynamic_object_descriptions']) {
					
					foreach ($arr_options['dynamic_object_descriptions'] as $object_description_id) {
						
						if ($_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_description['object_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, $object_description_id)) {
							continue;
						}
						
						$return .= '<th class="limit'.(!$arr_type_set['type']['object_name_in_overview'] ? ' max' : '').'"><span>'.Labels::parseTextVariables($arr_type_set['object_descriptions'][$object_description_id]['object_description_name']).'</span></th>';
					}
				} else {
					
					$nr_column = 0;
					
					foreach ($arr_type_set['object_descriptions'] as $object_description_id => $arr_object_description) {
					
						if (!$arr_object_description['object_description_in_overview'] || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_description['object_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, $object_description_id)) {
							continue;
						}
						
						$return .= '<th class="limit'.(!$arr_type_set['type']['object_name_in_overview'] && $nr_column == 0 ? ' max' : '').'"><span>'.Labels::parseTextVariables($arr_object_description['object_description_name']).'</span></th>';
						
						$nr_column++;
					}
				}

			$return .= '</tr></thead>
			<tbody>
				<tr>
					<td colspan="'.(count($arr_type_set['object_descriptions'])).'" class="empty">'.getLabel('msg_loading_server_data').'</td>
				</tr>
			</tbody>
		</table>';
		
		return $return;
	}
	
	public static function createViewExternal($resource_id, $arr_options = [], $pause = false) {

		// $arr_options = select/filter

		$option = 'load';
		if ($arr_options['session']) {
			$option = 'session';
		}
		if ($arr_options['select']) {
			$setting = 'select';
		}
		if ($arr_options['filter']) {
			$filter = true;
		}
		
		$arr_resource = data_linked_data::getLinkedDataResources($resource_id);
		$external = new ExternalResource($arr_resource);
		
		$arr_values = $external->getValues();
		
		$return = cms_general::createDataTableHeading('d:data_view:data_external-'.$resource_id.'_'.$option.'_'.$setting, ['filter' => ($filter ? 'y:data_filter:open_filter_external-'.$resource_id : false), 'pause' => $pause, 'delay' => 2]).'
			<thead><tr>
				'.($setting == 'select' ? '<th class="disable-sort"></th>' : '').'
				<th class="max limit" data-sort="asc-0"><span>'.getLabel('lbl_name').'</span></th>
				<th class="max limit"><span>URI</span></th>';
				
				foreach ($arr_values as $name => $value) {

					$return .= '<th class="limit"><span>'.Labels::parseTextVariables($name).'</span></th>';
				}

			$return .= '</tr></thead>
			<tbody>
				<tr>
					<td colspan="'.(($setting == 'select' ? 1 : 0)+2+count($arr_values)).'" class="empty">'.getLabel('msg_loading_server_data').'</td>
				</tr>
			</tbody>
		</table>';
		
		return $return;
	}
	
	public static function css() {
	
		$return = '	.view_type_object .overview h1 > span { vertical-align: middle; }
					.view_type_object .referenced .statistics > ul { max-width: 1000px; }
					.view_type_object .referenced .statistics > ul > li { display: inline-block; margin: 10px; }
					.view_type_object.object-sub h2,
					.view_type_object .referenced .statistics > ul > li > h2 { margin: 0px; padding: 4px 10px; display: inline-block; }
					.view_type_object.object-sub .record,
					.view_type_object .referenced .statistics > ul > li > .record { margin: 0px; padding: 8px 10px; }
					.view_type_object .dynamic-object-descriptions td p { margin: 0; }
				';
		
		return $return;
	}
	
	public static function js() {
	
		$return = "
			SCRIPTER.dynamic('.view_type_object', function(elm_scripter) {
			
				elm_scripter.on('open', '.data_viewer', function(e) {
				
					if (e.target != e.currentTarget) {
						return;
					}

					elm_scripter.find('.marginalia').each(function() {
						new Marginalia($(this).prev('.body')); 
					});
				}).on('open', '.data_viewer .tabs.object-subs > div', function(e) {
				
					if (e.target != e.currentTarget) {
						return;
					}
					
					var elm_table = $(this).find('[id^=d\\\:data_view\\\:data_object_sub_details-]');
					
					if (!elm_table.length) {
						return;
					}
					
					COMMANDS.dataTableContinue(elm_table);
				}).on('command', '.data_viewer [id^=y\\\:data_entry\\\:edit_quick-]', function(e) {
					var cur = $(this);
					cur.data('target', function(data) {
						if (data) {
							elm_scripter.replaceWith(data);
						}
					});
				});
				
				// OBJECT REFERENCES
				
				elm_scripter.on('open', '.referenced > .tabs > div', function(e) {
					
					if (e.target != e.currentTarget) {
						return;
					}
					
					var elm_table = $(this).find('table[id^=d\\\:data_view\\\:data-]');

					if (!elm_table.length) {
						return;
					}
					
					COMMANDS.dataTableContinue(elm_table);
				}).on('change', '.referenced input[type=checkbox]', function() {
					
					var elm_target = $(this).closest('form').closest('div').find('table[id^=d\\\:data_view\\\:data-]');
					var value = (elm_target.data('value') ? elm_target.data('value') : {});
					value.reference_object_filter = JSON.stringify(serializeArrayByName($(this).closest('form')));
					
					COMMANDS.setData(elm_target, value);
					COMMANDS.dataTableRefresh(elm_target);
				});
							
				// HANDLE MARGINALIA
				
				elm_scripter.on('mouseenter hovertag', '.data_viewer .tag', function() {						
					var cur = $(this);
					cur.addClass('hovered_tag');
					cur.parents('.tag').removeClass('popup');
					var elm_body = cur.parent().closest('.body');
					var elm_marginalia = elm_body.next('div');
					var arr_type_object_group_ids = cur.attr('data-ids').split('|');
					for (var i in arr_type_object_group_ids) {
						elm_body.find('span.a[id*='+arr_type_object_group_ids[i]+']').add(elm_marginalia.find('p.'+arr_type_object_group_ids[i]+' span')).addClass('active_tag');
					}
				}).on('mouseenter', '.data_viewer .marginalia p', function() {
					var cur = $(this);
					var elm_marginalia = cur.parent().closest('.marginalia');
					var elm_body = elm_marginalia.prev('div');
					elm_body.find('span.a[id*='+$(this).attr('class')+']').add(elm_marginalia.find('p.'+cur.attr('class')+' span')).addClass('active_tag');
				}).on('mouseleave', '.data_viewer .tag.active_tag, .data_viewer .marginalia p span.active_tag', function() {
					var cur = $(this);
					$('.body span.a').addClass('popup');
					$('.body span.active_tag, .data_viewer .marginalia p span.active_tag').removeClass('active_tag');
					cur.removeClass('hovered_tag');
					var elm_body = cur.parent().closest('.body');
					elm_body.find('.hovered_tag').each(function() {
						SCRIPTER.triggerEvent(this, 'hovertag');
					});
				});
			});
			
			SCRIPTER.dynamic('.view_type_object', 'discussion');
		";
		
		return $return;
	}

	public function commands($method, $id, $value = "") {
	
		// INTERACT
			
		if ($method == "view_type_object") {
			
			if ($value && $value['dynamic_filtering']) {
				toolbar::enableDynamicFiltering();
			}
			
			$arr_ids = explode('|', $id);
			
			if (count($arr_ids) == 1) {
				
				$arr_id = explode('_', $id);
				$type_id = (int)$arr_id[0];
				$object_id = (int)$arr_id[1];
				
				if (!$object_id || !custom_projects::checkAccesType($type_id)) {
					return;
				}
				
				$return = self::createViewTypeObject($type_id, $object_id);
			} else {
			
				foreach ($arr_ids as $arr_type_object_id) {
					
					$arr_id = explode('_', $arr_type_object_id);
					$type_id = (int)$arr_id[0];
					$object_id = (int)$arr_id[1];
					
					if (!$object_id) {
						continue;
					}

					$arr_type_object_ids[$type_id][$object_id] = $object_id;
				}
				
				foreach ($arr_type_object_ids as $type_id => $arr_object_ids) {
					
					if (!custom_projects::checkAccesType($type_id)) {
						return;
					}
					
					$arr_type_set = StoreType::getTypeSet($type_id);
					
					$arr_html_tabs['links'][$type_id] = '<li><a href="#tab-review-'.$type_id.'">'.Labels::parseTextVariables($arr_type_set['type']['name']).'</a></li>';
					
					$return_tab = '<div id="tab-review-'.$type_id.'">';
					
					if (count($arr_object_ids) == 1) {
						
						$return_tab .= self::createViewTypeObject($type_id, current($arr_object_ids));
					} else {
						
						$arr_feedback = [$type_id => ['objects' => $arr_object_ids]] + (SiteStartVars::getFeedback('data_view_filter') ?: []);
						SiteEndVars::setFeedback('data_view_filter', $arr_feedback, true);
		
						$return_tab .= self::createViewTypeObjects($type_id, ['session' => true]);
					}
					
					$return_tab .= '</div>';
					
					$arr_html_tabs['content'][$type_id] = $return_tab;				
				}
				
				if ($arr_html_tabs) {
					
					$return = '<div class="tabs">
						<ul>
							'.implode('', $arr_html_tabs['links']).'
						</ul>
						'.implode('', $arr_html_tabs['content']).'
					</div>';
				}
			}
			
			$this->html = $return;
		}
		
		if ($method == "view_type_object_sub") {
			
			$arr_id = explode('_', $id);
			
			$type_id = (int)$arr_id[0];
			$object_id = (int)$arr_id[1];
			$object_sub_details_id = $arr_id[2];
			$sub_object_id = (int)$arr_id[3];
			$object_sub_object_id = (int)$arr_id[4];
			
			if (!$object_id || !custom_projects::checkAccesType($type_id)) {
				return;
			}
			
			$arr_project = cms_nodegoat_custom_projects::getProjects($_SESSION['custom_projects']['project_id']);
			$arr_type_set = cms_nodegoat_custom_projects::getTypeSetReferenced($type_id, $arr_project['types'][$type_id], 'view');
			
			if ($_SESSION['NODEGOAT_CLEARANCE'] < $arr_type_set['object_sub_details'][$object_sub_details_id]['object_sub_details']['object_sub_details_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $object_sub_details_id)) {
				return;
			}

			$filter = new FilterTypeObjects($type_id, 'all', false, $arr_type_set); // Could be a referenced sub-object
			$filter->setVersioning('added');
			$filter->setConditions('style_include', toolbar::getTypeConditions($type_id));
			$filter->setScope(['users' => $_SESSION['USER_ID'], 'types' => cms_nodegoat_custom_projects::getProjectScopeTypes($_SESSION['custom_projects']['project_id'])]);
			$filter->setSelection(['object' => true, 'object_descriptions' => []]);
			
			$filter->setFilter(['objects' => $object_id, 'object_subs' => $sub_object_id], true);
			
			$arr_object_set = current($filter->init());
	
			$this->html = self::createTypeObjectSub($type_id, $object_id, $arr_object_set['object_subs'][$sub_object_id]);
		}
		
		if ($method == "view_type_referenced_object") { 
			
			$arr_id = explode("_", $id);
			$type_id = (int)$arr_id[0];
			$object_id = (int)$arr_id[1];
			
			if (!$object_id || !custom_projects::checkAccesType($type_id)) {
				return;
			}
			
			$this->html = '<div class="view_type_object">'.self::createViewTypeObjectReferenced($type_id, $object_id).'</div>';
		}
					
		if ($method == "data") {

			$arr_id = explode("_", $id);
			$type_id = (int)$arr_id[0];
			$option = $arr_id[1];
			$setting = $arr_id[2];
			$use_project_filter = $arr_id[3];
			
			$arr_value = ($value ?: []);
			if ($arr_value && !is_array($arr_value)) {
				$arr_value = json_decode($arr_value, true);
			}
			
			if (!custom_projects::checkAccesType($type_id)) {
				return;
			}

			if (!$option) {
				return;
			}
			
			$option_session = false;
			$option_referenced_type = false;
			$option_referenced_object = false;
			$arr_option_dynamic_object_descriptions = false;
			
			if (strpos($option, '|') !== false) { // Advanced options
				
				$arr_option = explode('|', $option);
				
				$key_dynamic = array_search('dynamic', $arr_option);
				
				if ($key_dynamic !== false) {
				
					$arr_option_dynamic_object_descriptions = explode('+', $arr_option[$key_dynamic+1]);
					$arr_option_dynamic_object_descriptions = array_combine($arr_option_dynamic_object_descriptions, $arr_option_dynamic_object_descriptions);
				}
				
				$key_referenced_type = array_search('referenced0type', $arr_option);
				
				if ($key_referenced_type !== false) {
					
					$option_referenced_type = $arr_option[$key_referenced_type+1];

					$key_referenced_object = array_search('referenced0object', $arr_option);
					
					if ($key_referenced_object !== false) {
						
						$option_referenced_object = $arr_option[$key_referenced_object+1];
					}
				}
			} else {
				
				if ($option === 'session') {
					$option_session = true;
				}
			}

			$arr_project = cms_nodegoat_custom_projects::getProjects($_SESSION['custom_projects']['project_id']);
			$arr_type_set = StoreType::getTypeSet($type_id);
			
			if ($arr_option_dynamic_object_descriptions) {
								
				$filter = new FilterTypeObjects($type_id, 'all', true);
				$filter->setConditions('style', toolbar::getTypeConditions($type_id));
				$filter->setScope(['users' => $_SESSION['USER_ID'], 'types' => cms_nodegoat_custom_projects::getProjectScopeTypes($_SESSION['custom_projects']['project_id'])]);
			} else {
				
				$filter = new FilterTypeObjects($type_id, 'overview', true);
				$filter->setConditions('style', toolbar::getTypeConditions($type_id));
				$filter->setScope(['users' => $_SESSION['USER_ID'], 'types' => cms_nodegoat_custom_projects::getProjectScopeTypes($_SESSION['custom_projects']['project_id'])]);
			}
			
			$arr_selection = [['object' => true, 'object_descriptions' => [], 'object_sub_details' => []]];
			
			foreach ($arr_type_set['object_descriptions'] as $object_description_id => $arr_object_description) {
				
				if ((!$arr_object_description['object_description_in_overview'] && !$arr_option_dynamic_object_descriptions) || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_description['object_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, $object_description_id)) {
					continue;
				}
				
				$arr_selection['object_descriptions'][$object_description_id] = $object_description_id;
			}
			
			if ($arr_option_dynamic_object_descriptions) {
				
				foreach ($arr_option_dynamic_object_descriptions as $object_desciption_id => $object_desciption_id) {
					
					if ($arr_selection['object_descriptions'][$object_desciption_id]) {
						continue;
					}
					
					unset($arr_option_dynamic_object_descriptions[$object_desciption_id]);
				}
				
				$filter->setSelection(['object' => true, 'object_descriptions' => $arr_option_dynamic_object_descriptions, 'object_sub_details' => []]);
			} else {
				
				$filter->setSelection($arr_selection);
			}

			$arr_filter = [];
			
			if ($arr_value['use_visualise']) {
				
				$arr_filter_prepare = [];
				
				if ($arr_value['date_range']) { // Use active filters to evaluate results
					
					$arr_type_filters = toolbar::getFilter();
					$arr_filters = current($arr_type_filters);
					$source_type_id = key($arr_type_filters);
					
					$collect = data_visualise::getVisualisationCollector($source_type_id, $arr_filters, data_visualise::getTypeScope($source_type_id));
					$arr_collect_info = $collect->getResultInfo();

					foreach ($arr_collect_info['types'] as $cur_type_id => $arr_paths) {
						
						if ($cur_type_id != $type_id) {
							continue;
						}
							
						foreach ($arr_paths as $path) {
							$filter->setFilter($arr_collect_info['filters'][$path]);
						}
					}
				
					$arr_filter_prepare['date_int'] = ['start' => $arr_value['date_range']['min'], 'end' => $arr_value['date_range']['max']];
				} 
				
				foreach ((array)$arr_value['object_sub_ids'] as $object_sub_details_id => $arr_objects) {
					
					$arr_filter_prepare['objects'] = array_merge((array)$arr_filter_prepare['objects'], array_keys($arr_objects));
					
					/*foreach ($arr_objects as $object_id => $arr_object_sub_ids) {
						$arr_filter_prepare['object_subs'] = array_merge((array)$arr_filter_prepare['object_subs'], $arr_object_sub_ids);
					}*/
				}
				foreach ((array)$arr_value['object_descriptions_object_ids'] as $object_description_id => $arr_object_ids) {
						
					$arr_filter_prepare['objects'] = array_merge((array)$arr_filter_prepare['objects'], $arr_object_ids);
				}
				if ($arr_value['object_ids']) {
					
					$arr_filter_prepare['objects'] = array_merge((array)$arr_filter_prepare['objects'], $arr_value['object_ids']);
				}
				
				if (!$arr_filter_prepare) {
					
					$arr_filter_set = ['objects' => -1]; // Find nothing!
				} else {
					
					if ($arr_filter_prepare['object_subs']) {
						
						$arr_filter_set = ['object_subs' => $arr_filter_prepare['object_subs']];
					} else {
						
						$arr_filter_set = ['objects' => $arr_filter_prepare['objects']];
					}
					
					if ($arr_filter_prepare['date_int']) {
						
						$arr_filter_set['date_int'] = $arr_filter_prepare['date_int'];
					}
				}
				
				if ($arr_filter_set) {
					$filter->setFilter($arr_filter_set);
				}
			} else {
				
				if ($value && $arr_project['types'][$type_id]['type_filter_id'] && $value['filter_id'] == $arr_project['types'][$type_id]['type_filter_id']) {
					unset($value['filter_id']);
				}
								
				$arr_filter = data_filter::parseUserFilterInput($value);
			}
			
			if ($_POST['search']) {
				$arr_filter['search'] = $_POST['search'];
			}
			if ($option_session) {
				
				$arr_feedback = (SiteStartVars::getFeedback('data_view_filter') ?: []);
				
				if ($arr_feedback[$type_id]) {			
					$arr_filter[] = $arr_feedback[$type_id];
				}
			} else if ($option_referenced_object) { // Referenced object id
				
				$arr_filter['referenced_object'] = ['object_id' => $option_referenced_object];
				
				if ($arr_option_dynamic_object_descriptions) {
					
					$arr_filter['referenced_object']['filter'] = ['object_descriptions' => $arr_option_dynamic_object_descriptions];
				}
			}
			if ($arr_value['reference_object_filter']) {
				
				$arr_reference_object_filter = json_decode($arr_value['reference_object_filter'], true);
				$arr_reference_object_filter = (array_filter($arr_reference_object_filter) ?: 1); // Empty filter? Show nothing => 1.
				
				$arr_filter['referenced_object']['filter'] = $arr_reference_object_filter;
			}
			
			if ($arr_filter['object_versioning']['version']) {
				$filter->setVersioning('full');
			}
			if ($arr_filter) {
				$filter->setFilter($arr_filter);
			}
			
			if ($_POST['arr_order_column']) {
				
				foreach ($_POST['arr_order_column'] as $nr_order => list($nr_column, $str_direction)) {
				
					if ($setting == 'select') {
						$nr_column--;
					}
					
					if ($nr_column == 0 && $arr_type_set['type']['object_name_in_overview']) { // Object name
						
						$filter->setOrder(['object_name' => $str_direction]);
					} else {
						
						$count_column = ($arr_type_set['type']['object_name_in_overview'] ? 1 : 0);
						
						foreach ($arr_type_set['object_descriptions'] as $object_description_id => $arr_object_description) {
								
							if (!$arr_selection['object_descriptions'][$object_description_id] || ($arr_option_dynamic_object_descriptions && !$arr_option_dynamic_object_descriptions[$object_description_id])) {
								continue;
							}
							
							if ($nr_column == $count_column) {
								$filter->setOrder([$object_description_id => $str_direction]);
							}
							$count_column++;
						}
					}
				}
			} else {
				
				$filter->setOrder(['date' => 'desc']); // Object dating
			}
			
			if (isset($_POST['nr_records_start']) && $_POST['nr_records_length'] != '-1') {
				$filter->setLimit([$_POST['nr_records_start'], $_POST['nr_records_length']]);
			}
			
			if ($arr_project['types'][$type_id]['type_filter_id']) {
				
				if ($use_project_filter || $_SESSION['NODEGOAT_CLEARANCE'] < NODEGOAT_CLEARANCE_UNDER_REVIEW) {
					
					$arr_use_project_ids = array_keys($arr_project['use_projects']);
					
					$arr_project_filters = cms_nodegoat_custom_projects::getProjectTypeFilters($_SESSION['custom_projects']['project_id'], false, false, $arr_project['types'][$type_id]['type_filter_id'], true, $arr_use_project_ids);
					$filter->setFilter(FilterTypeObjects::convertFilterInput($arr_project_filters['object']));
				}
			}

			$arr = $filter->init();
			$arr_info = $filter->getResultInfo();

			$arr_output = [
				'echo' => intval($_POST['echo']),
				'total_records' => $arr_info['total'],
				'total_records_filtered' => $arr_info['total_filtered'],
				'data' => []
			];
			
			foreach ($arr as $arr_object) {
				
				$count = 0;
				
				$arr_data = [];
				
				$arr_data['id'] = 'x:data_view:type_object_id-'.$type_id.'_'.$arr_object['object']['object_id'].'';
				$arr_data['class'] = 'popup';
				$arr_data['attr']['data-method'] = 'view_type_object';
				if ($setting == 'select') {
					$arr_data[] = '<input name="type_object_id" value="'.$arr_object['object']['object_id'].'" type="radio" />';
					$count++;
				}
				
				if ($arr_type_set['type']['object_name_in_overview']) {
					
					$arr_data['cell'][$count]['attr']['style'] = $arr_object['object']['object_name_style'];
					$arr_data[] = $arr_object['object']['object_name'];
					$count++;
				}
				
				foreach ($arr_object['object_definitions'] as $object_description_id => $arr_object_definition) {
					
					$arr_object_description = $arr_type_set['object_descriptions'][$object_description_id];
					
					if ($arr_option_dynamic_object_descriptions) {
						
						$arr_value = $arr_object_definition['object_definition_ref_object_id'][$option_referenced_type][$option_referenced_object]['object_definition_ref_value'];
						
						if ($arr_value) {
							$arr_data[] = '<p>'.implode('</p><p>', $arr_value).'</p>';
						} else {
							$arr_data[] = '';
						}
					} else {
						
						if ($arr_object_definition['object_definition_style']) {
						
							if ($arr_object_definition['object_definition_style'] == 'hide') {
								
								$arr_data[] = '';
								$count++;
								
								continue;
							}
							
							$arr_data['cell'][$count]['attr']['style'] = $arr_object_definition['object_definition_style'];
						}
						
						if ($arr_object_description['object_description_ref_type_id']) {
							
							if ($arr_object_description['object_description_is_dynamic']) {
							
								$arr_html = [];
								
								foreach ($arr_object_definition['object_definition_ref_object_id'] as $ref_type_id => $arr_ref_objects) {
								
									foreach($arr_ref_objects as $cur_object_id => $arr_reference) {
										
										$arr_html[] = data_view::createTypeObjectLink($ref_type_id, $cur_object_id, $arr_reference['object_definition_ref_object_name']);
									}
								}
								
								$arr_data[] = implode(', ', $arr_html);
							} else if ($arr_object_description['object_description_has_multi']) {
								
								$arr_html = [];
								
								foreach ($arr_object_definition['object_definition_ref_object_id'] as $key => $value) {
									
									$arr_html[] = data_view::createTypeObjectLink($arr_object_description['object_description_ref_type_id'], $value, $arr_object_definition['object_definition_value'][$key]);
								}
								
								$arr_data[] = implode(', ', $arr_html);
							} else {
								
								$arr_data[] = data_view::createTypeObjectLink($arr_object_description['object_description_ref_type_id'], $arr_object_definition['object_definition_ref_object_id'], $arr_object_definition['object_definition_value']);
							}
						} else {
							
							$str_value = arrParseRecursive($arr_object_definition['object_definition_value'], ['Labels', 'parseLanguage']);
							
							$arr_data[] = StoreTypeObjects::formatToPreviewValue($arr_object_description['object_description_value_type'], $str_value);
						}
					}
					
					$count++;
				}
				$arr_output['data'][] = $arr_data;
			}
			
			$this->data = $arr_output;
		}
		
		if ($method == "data_object_sub_details") {

			$arr_id = explode('_', $id);
			$type_id = (int)$arr_id[0];
			$object_id = $arr_id[1];
			$object_sub_details_id = $arr_id[2];
			$use_project_filter = $arr_id[3];
			
			if (!custom_projects::checkAccesType($type_id)) {
				return;
			}
			
			$arr_project = cms_nodegoat_custom_projects::getProjects($_SESSION['custom_projects']['project_id']);
			$arr_use_project_ids = array_keys($arr_project['use_projects']);
			
			$arr_types = StoreType::getTypes(array_keys($arr_project['types']));
			
			$arr_type_set = cms_nodegoat_custom_projects::getTypeSetReferenced($type_id, $arr_project['types'][$type_id], 'view');
			$arr_object_sub_details = $arr_type_set['object_sub_details'][$object_sub_details_id];
			
			if ($object_sub_details_id != 'all' && ($_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_sub_details['object_sub_details']['object_sub_details_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $object_sub_details_id))) {
				return;
			}
			
			$filter = new FilterTypeObjects($type_id, 'all', true, $arr_type_set);
			$filter->setScope(['users' => $_SESSION['USER_ID'], 'types' => cms_nodegoat_custom_projects::getProjectScopeTypes($_SESSION['custom_projects']['project_id'])]);
			$filter->setConditions('style', toolbar::getTypeConditions($type_id));
			
			$arr_selection = ['object' => [], 'object_descriptions' => [], 'object_sub_details' => [$object_sub_details_id => ['object_sub_details' => true, 'object_sub_descriptions' => []]]];
			$arr_filter_object_sub_details_ids = [];
			
			if ($object_sub_details_id != 'all') {
		
				foreach ($arr_type_set['object_sub_details'][$object_sub_details_id]['object_sub_descriptions'] as $object_sub_description_id => $arr_object_sub_description) {
					
					if (!$arr_object_sub_description['object_sub_description_in_overview'] || $_SESSION['NODEGOAT_CLEARANCE'] < $arr_object_sub_description['object_sub_description_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $object_sub_details_id, $object_sub_description_id)) {
						continue;
					}
					
					$arr_selection['object_sub_details'][$object_sub_details_id]['object_sub_descriptions'][$object_sub_description_id] = $object_sub_description_id;
				}
				
				$filter->setSelection($arr_selection);
				
				$cur_type_id = $arr_object_sub_details['object_sub_details']['object_sub_details_type_id']; // Cross-referenced sub-object
					
				if ($cur_type_id) {

					if ($arr_project['types'][$cur_type_id]['type_filter_id']) {
				
						if ($use_project_filter || $_SESSION['NODEGOAT_CLEARANCE'] < NODEGOAT_CLEARANCE_UNDER_REVIEW) {
							
							$arr_project_filters = cms_nodegoat_custom_projects::getProjectTypeFilters($_SESSION['custom_projects']['project_id'], false, false, $arr_project['types'][$cur_type_id]['type_filter_id'], true, $arr_use_project_ids);
							$arr_filter_form = FilterTypeObjects::convertFilterInput($arr_project_filters['object']);
							
							$arr_filter_referenced_object_sub_details = [];

							$arr_filter_referenced_object_sub_details['object_filter'][]['object_subs'][$object_sub_details_id]['object_sub_referenced'][] = $arr_filter_form;
							
							$filter->setFilter($arr_filter_referenced_object_sub_details, true);
						}
					}
				}
			} else {
				
				$filter->setSelection($arr_selection);
				
				foreach ($arr_type_set['object_sub_details'] as $cur_object_sub_details_id => $arr_cur_object_sub_details) {
					
					if ($_SESSION['NODEGOAT_CLEARANCE'] < $arr_cur_object_sub_details['object_sub_details']['object_sub_details_clearance_view'] || !custom_projects::checkClearanceTypeConfiguration('view', $arr_project['types'][$type_id], $arr_type_set, false, $cur_object_sub_details_id)) {
						continue;
					}
					
					$cur_type_id = $arr_cur_object_sub_details['object_sub_details']['object_sub_details_type_id']; // Cross-referenced sub-object
					
					if ($cur_type_id) {

						if ($arr_project['types'][$cur_type_id]['type_filter_id']) {
				
							if ($use_project_filter || $_SESSION['NODEGOAT_CLEARANCE'] < NODEGOAT_CLEARANCE_UNDER_REVIEW) {
								
								$arr_project_filters = cms_nodegoat_custom_projects::getProjectTypeFilters($_SESSION['custom_projects']['project_id'], false, false, $arr_project['types'][$cur_type_id]['type_filter_id'], true, $arr_use_project_ids);
								$arr_filter_form = FilterTypeObjects::convertFilterInput($arr_project_filters['object']);
								
								$arr_filter_referenced_object_sub_details = [];
								
								$arr_filter_referenced_object_sub_details['object_filter'][]['object_subs'][$cur_object_sub_details_id]['object_sub_referenced'][] = $arr_filter_form;
								
								$filter->setFilter($arr_filter_referenced_object_sub_details, true);
							}
						}
					}
					
					$arr_filter_object_sub_details_ids[] = $cur_object_sub_details_id;
				}
			}

			if ($arr_filter_object_sub_details_ids) {
				$filter->setFilter(['objects' => $object_id, 'object_sub_details' => $arr_filter_object_sub_details_ids], true);
			} else {
				$filter->setFilter(['objects' => $object_id]);
			}
			
			if ($value && $arr_project['types'][$type_id]['type_filter_id'] && $value['filter_id'] == $arr_project['types'][$type_id]['type_filter_id']) {
				unset($value['filter_id']);
			}
						
			$arr_filter = data_filter::parseUserFilterInput($value);
			
			if ($_POST['search']) {
				$arr_filter['search'][] = $_POST['search'];
			}
			if (!$arr_filter['object_versioning'] || $arr_filter['object_versioning']['version']) {
				$filter->setVersioning(($arr_filter['object_versioning']['version'] ? 'full' : 'added'));
			}
			if ($arr_filter) {
				$filter->setFilter($arr_filter, true);
			}
			if ($_POST['arr_order_column']) {
				
				foreach ($_POST['arr_order_column'] as $nr_order => list($nr_column, $str_direction)) {
					
					if ($object_sub_details_id == 'all') { // Object sub details combined
					
						$sort_id = ($nr_column == 0 ? 'object_sub_details_name' : ($nr_column == 1 ? 'object_sub_date_start' : ($nr_column == 2 ? 'object_sub_date_end' : '')));
						$filter->setOrderObjectSubs($object_sub_details_id, [$sort_id => $str_direction]);
					} else {
						
						if (!$arr_object_sub_details['object_sub_details']['object_sub_details_has_date']) {
							$nr_column += 2;
						}
						if (!$arr_object_sub_details['object_sub_details']['object_sub_details_has_location']) {
							$nr_column += 2;
						}
						
						if ($nr_column <= 1) {
							$sort_id = ($nr_column == 0 ? 'object_sub_date_start' : ($nr_column == 1 ? 'object_sub_date_end' : ''));
							$filter->setOrderObjectSubs($object_sub_details_id, [$sort_id => $str_direction]);
						} else {
							$count_column = 4;
							foreach ($arr_object_sub_details['object_sub_descriptions'] as $object_sub_description_id => $arr_sub_object_description) {
								
								if (!$arr_selection['object_sub_details'][$object_sub_details_id]['object_sub_descriptions'][$object_sub_description_id]) {
									continue;
								}
															
								if ($nr_column == $count_column) {
									$filter->setOrderObjectSubs($object_sub_details_id, [$object_sub_description_id => $str_direction]);
								}
								$count_column++;
							}
						}
					}
				}
			}
			if (isset($_POST['nr_records_start']) && $_POST['nr_records_length'] != '-1') {
				$filter->setLimitObjectSubs($object_sub_details_id, [$_POST['nr_records_start'], $_POST['nr_records_length']]);
			}
			
			if ($arr_project['types'][$type_id]['type_filter_id']) {
				
				if ($use_project_filter || $_SESSION['NODEGOAT_CLEARANCE'] < NODEGOAT_CLEARANCE_UNDER_REVIEW) {
					
					$arr_project_filters = cms_nodegoat_custom_projects::getProjectTypeFilters($_SESSION['custom_projects']['project_id'], false, false, $arr_project['types'][$type_id]['type_filter_id'], true, $arr_use_project_ids);
					$filter->setFilter(FilterTypeObjects::convertFilterInput($arr_project_filters['object']), $arr_project['types'][$type_id]['type_filter_object_subs']);
				}
			}

			$arr = $filter->init();
			$arr_info = $filter->getResultInfoObjectSubs($object_id, $object_sub_details_id);

			$arr_output = [
				'echo' => intval($_POST['echo']),
				'total_records' => $arr_info['total'],
				'total_records_filtered' => $arr_info['total_filtered'],
				'data' => []
			];
			
			$arr_object_subs = $arr[$object_id]['object_subs'];

			foreach ((array)$arr_object_subs as $arr_object_sub) {
				
				$count = 0;
				$cur_object_sub_details_id = $arr_object_sub['object_sub']['object_sub_details_id'];
				
				$arr_data = [];
				
				$arr_data['id'] = 'x:data_view:object_sub_id-'.$type_id.'_'.$object_id.'_'.$cur_object_sub_details_id.'_'.$arr_object_sub['object_sub']['object_sub_id'].'_'.(int)$arr_object_sub['object_sub']['object_sub_object_id'];
				$arr_data['class'] = 'popup';
				$arr_data['attr']['data-method'] = 'view_type_object_sub';
				
				$arr_object_sub_values = self::formatToTypeObjectSubValues($type_id, $cur_object_sub_details_id, $object_id, $arr_object_sub);
				
				if ($object_sub_details_id == 'all') {
					
					$arr_object_sub_details = $arr_type_set['object_sub_details'][$cur_object_sub_details_id]['object_sub_details'];

					$arr_data[] = ($arr_object_sub_details['object_sub_details_type_id'] ?
							'<span class="icon" data-category="direction" title="'.getLabel('lbl_referenced').'">'.getIcon('leftright-right').'</span><span>'.Labels::parseTextVariables($arr_types[$arr_object_sub_details['object_sub_details_type_id']]['name']).'</span> '
						: '').'<span class="sub-name">'.Labels::parseTextVariables($arr_object_sub_details['object_sub_details_name']).'</span>';
						
					$count++;
					
					$count += 4;
				} else {
					
					if (!$arr_object_sub_details['object_sub_details']['object_sub_details_has_date']) {
						unset($arr_object_sub_values['object_sub_date_start'], $arr_object_sub_values['object_sub_date_end']);
					} else {
						$count += 2;
					}
					if (!$arr_object_sub_details['object_sub_details']['object_sub_details_has_location']) {
						unset($arr_object_sub_values['object_sub_location_reference_label'], $arr_object_sub_values['object_sub_location_reference_value']);
					} else {
						$count += 2;
					}
				}
			
				$arr_data = array_merge($arr_data, array_values($arr_object_sub_values));
				
				foreach ($arr_object_sub['object_sub_definitions'] as $object_sub_description_id => $arr_object_sub_definition) {
					
					if (!isset($arr_object_sub_values['object_sub_definition_'.$object_sub_description_id])) {
						continue;
					}
					
					if ($arr_object_sub_definition['object_sub_definition_style'] && $arr_object_sub_definition['object_sub_definition_style'] != 'hide') {
						
						$arr_data['cell'][$count]['attr']['style'] = $arr_object_sub_definition['object_sub_definition_style'];
					}
					
					$count++;
				}
				
				$arr_output['data'][] = $arr_data;
			}
			
			$this->data = $arr_output;
		}
		
		if ($method == "data_external") {

			$arr_id = explode("_", $id);
			$resource_id = (int)$arr_id[0];
			$option = $arr_id[1];
			$setting = $arr_id[2];
			$arr_value = ($value ?: []);
			if ($arr_value && !is_array($arr_value)) {
				$arr_value = json_decode($arr_value, true);
			}

			if (!$option) {
				return;
			}
			
			$arr_resource = data_linked_data::getLinkedDataResources($resource_id);
			$external = new ExternalResource($arr_resource);
			$arr_values = $external->getValues();
			
			if ($arr_value['form']) {
				$arr_filter = $arr_value['form'];
			}
			if ($_POST['search']) {
				$arr_filter['name'] = $_POST['search'];
			}
			
			if (isset($_POST['sorting_column_0'])) {
				
				$nr_column = $_POST['sorting_column_0'];
				if ($setting == 'select') {
					$nr_column--;
				}
				
				if ($nr_column == 0) { // Object name
					$external->setOrder(['name' => $_POST['sorting_direction_0']]);
				} else {

					$count_column = 1;
					
					foreach ($arr_values as $name => $value) {
							
						if ($nr_column == $count_column) {
							$external->setOrder([$name => $_POST['sorting_direction_0']]);
						}
						$count_column++;
					}
				}
			}
			if (isset($_POST['nr_records_start']) && $_POST['nr_records_length'] != '-1') {
				$external->setLimit([$_POST['nr_records_start'], $_POST['nr_records_length']]);
			}
			
			$external->setFilter($arr_filter);
			$arr = $external->request();
			$arr = $arr['values'];

			$arr_output = [
				'echo' => intval($_POST['echo']),
				'total_records' => 10000,
				'total_records_filtered' => 10000,
				'data' => []
			];
			
			foreach ($arr as $arr_result) {
				
				$arr_data = [];
				//$arr_data["id"] = "x:data_view:type_object_id-".$type_id."_".$arr_object["object"]["object_id"]."";
				//$arr_data["class"] = 'popup';
				//$arr_data["attr"]['data-method'] = 'view_type_object';
				if ($setting == 'select') {
					$arr_data[] = '<input name="uri" value="'.htmlspecialchars($arr_result['uri']).'" type="radio" />';
				}
				$arr_data[] = $arr_result['label'];
				$arr_data[] = FormatBBCode::formatUrls($arr_result['uri']);
				
				foreach ($arr_values as $name => $value) {
					
					$arr_data[] = FormatBBCode::formatUrls($arr_result[$name]);
				}
				
				$arr_output['data'][] = $arr_data;
			}
			
			$this->data = $arr_output;
		}
	}
}
