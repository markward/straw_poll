<?php
class block_straw_poll extends block_base {
    private $blockempty = true;
	
	public function init() {
		global $USER;
        $this->title = get_string('pluginname', 'block_straw_poll');
    }
	
	public function get_content(){
		if ($this->content !== null){
			return $this->content;
		}
		if(!is_object($this->config)){
			return false;
		}
		global $USER, $DB;
		
		$instanceid = optional_param('spinstance', 0, PARAM_INT);
		$choiceid = optional_param('choice', 0, PARAM_INT);
		$userresponded = 0;
		
		$dbresponse = $DB->get_record('block_straw_poll_responses', array('userid'=>$USER->id, 'instanceid' => $this->instance->id));
		if($dbresponse && $dbresponse->choice < 7){
			$userresponded = $dbresponse->choice;
		}
		
		if($this->config->ends > time() && $instanceid == $this->instance->id && $choiceid > 0 && $choiceid < 7){
			if($userresponded > 0 && !$this->config->canchange){
				//debugging('user cannot update choice');
			}
			else{
				if($userresponded > 0){
					$response = $dbresponse;
					
					$branch = 'choice'.$choiceid;
					if($response->choice != $choiceid && $this->config->$branch != ''){
						//no point updating if it hasnt changed
						$response->choice = $choiceid;
						$DB->update_record('block_straw_poll_responses', $response);	
					}
				}
				else{
					$response = new stdclass();
					$response->instanceid = $this->instance->id;
					$response->choice = $choiceid;
					$response->userid = $USER->id;
					
					$branch = 'choice'.$choiceid;
					if($this->config->$branch != ''){
						$response->id = $DB->insert_record('block_straw_poll_responses', $response);	
					}
				}
				$userresponded = $choiceid;
			}
		}
		
		$this->content =  new stdClass;
		$this->content->text = html_writer::tag('h2', $this->config->question, array('class'=>'question'));
		
		//the answers
		if($this->config->ends > time() && has_capability('block/straw_poll:respond', $this->page->context)){
			if($this->config->canchange || $userresponded < 1){
				if(isloggedin()){
					$this->content->text .= html_writer::start_tag('ul', array('class'=>'choices'));
					for ($choice = 1; $choice <= 6; $choice++){
						$branch = 'choice'.$choice;
						if($this->config->$branch != ''){
							$url = new moodle_url($this->page->url, array('spinstance'=>$this->instance->id,'choice'=>$choice));

							$attributes = array('class'=>'choice');
							if($userresponded == $choice){
								$attributes['class'] .= ' chosen';
							}
							
							$this->content->text .= html_writer::start_tag('li', $attributes);
							
							$this->content->text .= html_writer::link($url, $this->config->$branch);
							
							$this->content->text .= html_writer::end_tag('li');
						}
					}
					$this->content->text .= html_writer::end_tag('ul');
				}
				$this->blockempty = false;
			}
		}
		
		//the results
		if((has_capability('moodle/block:edit', $this->page->context) || $this->config->seeresults == 0) ||
		 ($this->config->seeresults == 1 && $userresponded) ||
		 ($this->config->seeresults == 2 && $this->config->ends < time())){
			$this->get_results();
		}
		
		if($this->blockempty){
			if($userresponded){
				//user has given an answer but nothing has printed, we need to thank them for voting
				$this->content->text .= html_writer::tag('p', get_string('thanks', 'block_straw_poll', date('l jS F Y',$this->config->ends)));
			}
			else{
				$this->content->text .= html_writer::tag('p', get_string('cantvote', 'block_straw_poll'));
			}
		}
		$this->content->footer = html_writer::tag('p', get_string('pollcloses', 'block_straw_poll', date('l jS F Y',$this->config->ends)));
		
		return $this->content;
		
	}	
	
    /**
    * Function that can be overridden to do extra cleanup before
    * the database tables are deleted. (Called once per block, not per instance!)
    */
    public function before_delete() {
		return true;
    }
 
    /**
    * Delete everything related to this instance if you have been using persistent storage other than the configdata field.
    * @return boolean
    */
    function instance_delete() {
		global $DB;
		$DB->delete_records('block_straw_poll_responses', array('instanceid'=>$this->instance->id));
        return true;
    }
 
    /**
    * Are you going to allow multiple instances of each block?
    * If yes, then it is assumed that the block WILL USE per-instance configuration
    * @return boolean
    */
    function instance_allow_multiple() {
        // Are you going to allow multiple instances of each block?
        // If yes, then it is assumed that the block WILL USE per-instance configuration
        return true;
    }
	
	private function get_results(){
		global $DB;
		$responses = $DB->get_records('block_straw_poll_responses', array('instanceid'=>$this->instance->id));
		if(count($responses)){
			$this->blockempty = false;
			$chartdata = array();
			$total = 0;
			foreach($responses as $record){
				$total++;
				if(!isset($chartdata[$record->choice])){
					$chartdata[$record->choice] = new stdclass();
					$branch = 'choice'.$record->choice;
					$chartdata[$record->choice]->category = $this->config->$branch;
					$chartdata[$record->choice]->votes = 1;
					
				}
				else{
					$chartdata[$record->choice]->votes++;
				}
			}
			$this->content->text .= html_writer::start_tag('div', array('id'=>'chart'));
			$i = 0;
			for ($choice = 1; $choice <= 6; $choice++){
				$branch = 'choice'.$choice;		
				if($this->config->$branch != ''){
				
					if(!isset($chartdata[$choice])){
						$chartdata[$choice] = new stdclass();
						$chartdata[$choice]->category = $this->config->$branch;
						$chartdata[$choice]->votes = 0;
					}
					$percentage = ($chartdata[$choice]->votes / $total)*100;
					$this->content->text .= html_writer::tag('p', $chartdata[$choice]->category.': '.$chartdata[$choice]->votes.' '.get_string('votes', 'block_straw_poll'),array('class'=>'choicetext choice'.$choice));
					if($chartdata[$choice]->votes > 0){
						$this->content->text .= html_writer::tag('div', number_format($percentage,0).'%', array('class'=>'pcentbar tone'.$i, 'style'=>'width:'.$percentage.'%;'));
					}
					else{
						$this->content->text .= html_writer::tag('div', '', array('class'=>'pcentbar tone'.$i, 'style'=>'width:'.$percentage.'%;'));
					}
					$i = 1 - $i;
					
				}
			}
			$this->content->text .= html_writer::end_tag('div');
		}
	}
}



?>