<?php 
	
namespace Drupal\import_json\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Drupal\user\Plugin\views\argument_default;
use Drupal\group\Entity\Group;
//use \Drupal\group\Entity\Group;
use \Drupal\file\Entity\File;
use \Drupal\user\Entity\User;

class import_json extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   */
  public function content() {
	
		// Path for module file.
		$module_path = drupal_get_path('module','import_json');

		// Adding Member with member json.
		$member_json_path = $module_path . '/json/export_napw_users.json';
		$member_json_data = file_get_contents($member_json_path);
		$member_json_array_data = Json::decode( $member_json_data, true );
		
		foreach($member_json_array_data as $member_single_data){
			
			
			if(!user_load_by_mail($member_single_data['email'])){

				$member_timestamp = strtotime($member_single_data['created_at']['$date']);
				$member_new_data = User::create([
					'name' => $member_single_data['email'],    
					'mail' => $member_single_data['email'],
					'pass' => 'test',
					'status' => 1,
					'created' => $member_timestamp,
					'field_user_oid' => $member_single_data['_id']['$oid'],
					'roles' => array('authenticated'),
					'path' =>  ['alias' => '/member/'. $member_single_data['_slugs'][0]],
				]);

				$member_new_data->save();

				// Setting profile values.
				$profile_type = 'profile';
				$profile = \Drupal::entityManager()->getStorage('profile')
					->loadByUser($member_new_data, $profile_type);
				if(!$profile) {
					$profile =  \Drupal\profile\Entity\Profile::create([
						'uid' => $member_new_data->id(),
						'type' => $profile_type,
					]);
				}

				$profile->set('field_profile_first_name', $member_single_data['fn']);
				$profile->set('field_profile_last_name', $member_single_data['ln']);
				$profile->field_profile_address->country_code = $member_single_data['contact']['addresses'][0]['cc'];
				$profile->field_profile_address->address_line1 = $member_single_data['contact']['addresses'][0]['rd'];
				$profile->field_profile_address->administrative_area = $member_single_data['contact']['addresses'][0]['st'];
				$profile->field_profile_address->locality = $member_single_data['contact']['addresses'][0]['ct'];
				$profile->field_profile_address->postal_code = $member_single_data['contact']['addresses'][0]['zp'];
				$profile->set('field_profile_phone_number', $member_single_data['contact']['phones'][0]['pn']);
				$profile->set('field_profile_self_introduction', $member_single_data['experiences'][1]['ti']);

				// Save Image in entity.
				$member_image_data = file_get_contents( $module_path . "/images/profile.jpg" );
				$member_stored_image_data = file_save_data($member_image_data, "public://group_image.jpg", FILE_EXISTS_REPLACE);
				$profile->field_profile_image->setValue([
								'target_id' => $member_stored_image_data->id(),
							]);

				$profile->save();
			}
			
		}

		// Adding Groups and its data/
		$json_path = $module_path . '/json/export_napw_groups2.json';	
		$group_json_data = file_get_contents($json_path);
		$json_array_data = Json::decode( $group_json_data, true );
		$existing_group_query = '';	
    foreach($json_array_data as $group_data){
				
				// Save Image in entity.
				$image_data = file_get_contents( $module_path . "/images/group_image.jpg" );
				$stored_image_data = file_save_data($image_data, "public://styles/social_xx_large/public/2017-11/group_image.jpg", FILE_EXISTS_REPLACE);
			
				//To check wheter data is already inserted or not.
				$existing_group_query = \Drupal::entityQuery('group');
				$existing_group_query->condition('type', 'open_group');
				$existing_group_query->condition('field_oid', $group_data['_id']['$oid']);
				$group_id = $existing_group_query->execute();
				
				if(empty($group_id)){	
						// Create New group using JSON file.
						$new_group_detail = Group::create([
								'label' => $group_data['name'],
								'type' => 'open_group',
								'field_group_description' => $group_data['description'],
								'path' =>  ['alias' => '/company/'. $group_data['_slugs'][0]],
								'field_oid' => $group_data['_id']['$oid'],
								'field_group_image' => [
									'target_id' => $stored_image_data->id(),
									'alt' => 'Sample',
									'title' => 'Sample File'
								],
								'field_group_address' => [
									'country_code' => 'US',
									'address_line1' => '1098 Alta Ave',
									'locality' => 'Mountain View',
									'administrative_area' => 'US-CA',
									'postal_code' => '94043',
								],
								//'created' => $group_data['created_at']['$date'],
						]);
						$new_group_detail->save();
				}
				
				
			
    }
		
		// Adding members to them appropriate groups.
		$group_member_json_path = $module_path . '/json/export_napw_group_members.json';	
		$group_member_json_data = file_get_contents($group_member_json_path);
		$group_member_json_array_data = Json::decode( $group_member_json_data, true );
		
		foreach($group_member_json_array_data as $group_member_single){
			
			// Load group data.
			$existing_group_member_query = \Drupal::entityQuery('group');
			$existing_group_member_query->condition('type', 'open_group');
			$existing_group_member_query->condition('field_oid', $group_member_single['group_id']['$oid']);
			$group_id_with_oid = $existing_group_member_query->execute();
			
			
			$loaded_group = array();
			foreach($group_id_with_oid as $gid_single){
				$loaded_group = Group::load($gid_single);
			}

			
			// Load user data.
			$existing_user_query = \Drupal::entityQuery('user');
			$existing_user_query->condition('field_user_oid', $group_member_single['user_id']['$oid']);
			$user_id_with_oid = $existing_user_query->execute();
			
			foreach($user_id_with_oid as $uid_single){
				$loaded_user_data = User::load($uid_single);
			}
			
			if(!empty($loaded_group) && !empty($loaded_user_data)){
				$loaded_group->addMember($loaded_user_data);
			}
			
		}
	
    return array(
      '#type' => 'markup',
      '#markup' => $this->t('Data Imported!'),
    );
  }

}

?>