<?php
declare(strict_types=1);

namespace EclipsePhaseCharacterCreator\Backend;

/**
 * Character managment class
 *
 * @author Jigé
 */
class EPCharacterCreator implements Savable
{
    static $PSY_CHI_TRAIT_NAME = "Psi I";
    static $PSY_GAMMA_TRAIT_NAME = "Psi II";
    
    public $initialCreationPoints;
    public $aptitudePoints;
    public $reputationPoints;
    public $reputationPointsMorphMod;
    public $reputationPointsTraitMod;
    public $reputationPointsFactionMod;
    public $reputationPointsBackgroundMod;
    public $reputationPointsSoftGearMod;
    public $reputationPointsPsyMod;
    /**
     * @var EPConfigFile
     */
    public  $configValues;
    public  $errorList;
    /**
     * @var EPCharacter
     */
    public  $character;
    /**
     * @var EPListProvider
     */
    private $listProvider;
    public  $groups;
    public  $prefixs;
    public  $backgrounds;
    public  $factions;
    public  $morphs;
    public  $ais;
    public  $gears;
    public  $psySleights;
    public  $traits;
    public  $validation;
    public  $psySleigths;
    
    public $nativeLanguageSet;
    public $creationMode;
    
    public $evoRezPoint;
    public $evoRepPoint;
    public $evoCrePoint;
    
    public $evoCrePointPurchased;

    /**
     * @var EPCharacterCreator
     */
    public $back;
    
    // How many reputation points are remaining after all the modifyers are taken into account
    function getReputationPointsRemaining(){
            return $this->reputationPoints +
                   $this->reputationPointsMorphMod +
                   $this->reputationPointsTraitMod +
                   $this->reputationPointsFactionMod +
                   $this->reputationPointsBackgroundMod +
                   $this->reputationPointsSoftGearMod +
                   $this->reputationPointsPsyMod +
                   $this->evoRepPoint
                   - $this->getSumRepPoints();
    }
    function getReputationPoints(){
        return max(0,$this->getReputationPointsRemaining());
    }

    function getSavePack(): array
    {
		$savePack = array();
		
        $savePack['versionName'] = $this->configValues->getValue('GeneralValues','versionName');
        $savePack['versionNumber'] = $this->configValues->getValue('GeneralValues','versionNumber');
                
        $savePack['initialCreationPoints'] = $this->initialCreationPoints;
		$savePack['aptitudePoints'] = $this->aptitudePoints;
		$savePack['reputationPoints'] = $this->reputationPoints;
		
		$savePack['reputationPointsMorphMod'] = $this->reputationPointsMorphMod;
		$savePack['reputationPointsTraitMod'] = $this->reputationPointsTraitMod;
		$savePack['reputationPointsFactionMod'] = $this->reputationPointsFactionMod;
		$savePack['reputationPointsBackgroundMod'] = $this->reputationPointsBackgroundMod;
		$savePack['reputationPointsSoftGearMod'] = $this->reputationPointsSoftGearMod;
		$savePack['reputationPointsPsyMod'] = $this->reputationPointsPsyMod;
		
		$savePack['nativeLanguageSet'] = $this->nativeLanguageSet;
                
        $savePack['creationMode'] = $this->creationMode;
        $savePack['evoRezPoint'] = $this->evoRezPoint;
        $savePack['evoRepPoint'] = $this->evoRepPoint;
        $savePack['evoCrePoint'] = $this->evoCrePoint;
        $savePack['evoCrePointPurchased'] = $this->evoCrePointPurchased;
                		
		$savePack['charSavePack'] = $this->character->getSavePack();
		
		return $savePack;
		
    }
    
    function loadSavePack($savePack,$cc = null){
        $this->initialCreationPoints = $savePack['initialCreationPoints'];
		$this->aptitudePoints = $savePack['aptitudePoints'];
		$this->reputationPoints = $savePack['reputationPoints'];
		
		$this->reputationPointsMorphMod = $savePack['reputationPointsMorphMod'];
		$this->reputationPointsTraitMod = $savePack['reputationPointsTraitMod'];
		$this->reputationPointsFactionMod = $savePack['reputationPointsFactionMod'];
		$this->reputationPointsBackgroundMod = $savePack['reputationPointsBackgroundMod'];
		$this->reputationPointsSoftGearMod = $savePack['reputationPointsSoftGearMod'];
		$this->reputationPointsPsyMod = $savePack['reputationPointsPsyMod'];
		
		$this->nativeLanguageSet = $savePack['nativeLanguageSet'];
		
        $this->creationMode = $savePack['creationMode'];
        $this->evoRezPoint = $savePack['evoRezPoint'];
        $this->evoRepPoint = $savePack['evoRepPoint'];
        $this->evoCrePoint = $savePack['evoCrePoint'];
        $this->evoCrePointPurchased = $savePack['evoCrePointPurchased'];
		
		$this->character->loadSavePack($savePack['charSavePack'],$this);
		
		//last details after the load save pack 
		//set cc on stats
		$statsToComplete = $this->character->ego->stats;
		foreach($statsToComplete as $m){
			$m->cc = $this;
		}
		
		//set linked Apt on skill
		$skillToComplete = $this->character->ego->skills;
		foreach($skillToComplete as $m){
			$linkedApt = $this->listProvider->getSkillByNamePrefix($m->name,$m->prefix,$this->character->ego->aptitudes)->linkedApt;
			if($linkedApt == null){
				$linkedApt = $this->getAptitudeByAbbreviation($this->listProvider->getAptForPrefix($m->prefix));
			}
			$m->linkedApt = $linkedApt;
		}
		//------------
		
		//if(!empty($this->character->morphs)) $this->activateMorph($this->character->morphs[0]);
		
				
    }
    function __construct($pathToConfig,$amountCP = -1){
        $this->creationMode = true;
        $this->validation = new EPValidation();
        $this->evoRezPoint = 0;
        $this->evoRepPoint = 0;
        $this->evoCrePoint = 0;  
        $this->evoCrePointPurchased = 0;
        $this->init($pathToConfig,$amountCP);
    }
    function activateMorph($morph = null){
        if (!isset($morph)){
            $this->character->currentMorphUid = '';
            foreach ($this->getAptitudes() as $a){
                $a->activMorph = null;
            }
            $this->adjustAll();
            return true;
        }
        if (is_array($this->character->morphs)){
            foreach ($this->character->morphs as $m){
                if (strcmp($m->name,$morph->name) == 0){
                    $this->character->currentMorphUid = $m->getUid();
                    foreach ($this->getAptitudes() as $a){
                        $a->activMorph = $m;
                    }
                    $this->adjustAll();
                    return true;
                }
            }            
        }
        
        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Morph not exist in character morph list !)', EPCreatorErrors::$SYSTEM_ERROR));
        return false;
    }
    function addAI(EPAi $ai): bool
    {
        if ($this->creationMode){
            if ($ai->isInArray($this->character->ego->defaultAis)){
                return true;
            }        
            if ($ai->addToArray($this->character->ego->ais)){
                $this->adjustAll();
                return true;            
            }
            return false;            
        }else{
            if ($ai->isInArray($this->character->ego->defaultAis)){
                return true;
            }        
            if ($ai->addToArray($this->character->ego->ais)){
                $this->adjustAll();
                $this->evoCrePoint -= $ai->getCost();
                return true;            
            }
            return false;            
        }
    }
    function checkValidation(): bool
    {
        $this->validation->items[EPValidation::$APTITUDE_POINT_USE] = $this->aptitudePoints == 0;
        $this->validation->items[EPValidation::$REPUTATION_POINT_USE] = $this->getReputationPoints() == 0;
        $this->validation->items[EPValidation::$BACKGROUND_CHOICE] = !empty($this->character->ego->background);
        $this->validation->items[EPValidation::$FACTION_CHOICE] = !empty($this->character->ego->faction);
        $this->validation->items[EPValidation::$CHARACTER_NAME_CHOICE] = !empty($this->character->charName);
        $this->validation->items[EPValidation::$MORPH_CHOICE] = is_array($this->character->morphs) && count($this->character->morphs) > 0;
        $this->validation->items[EPValidation::$MOTIVATION_THREE_CHOICE] = is_array($this->character->ego->motivations) && count($this->character->ego->motivations) >= 3;
        $this->validation->items[EPValidation::$ACTIVE_SKILLS_MIN] = $this->getActiveRestNeed() == 0;
        $this->validation->items[EPValidation::$KNOWLEDGE_SKILLS_MIN] = $this->getKnowledgeRestNeed() == 0;
        $this->validation->items[EPValidation::$CREDIT_AMOUNT_ENOUGH] = $this->getCredit() >= 0;
  
        return  $this->validation->items[EPValidation::$APTITUDE_POINT_USE] &&
                $this->validation->items[EPValidation::$REPUTATION_POINT_USE] &&
                $this->validation->items[EPValidation::$BACKGROUND_CHOICE] &&
                $this->validation->items[EPValidation::$FACTION_CHOICE] &&
                $this->validation->items[EPValidation::$CHARACTER_NAME_CHOICE] &&
                $this->validation->items[EPValidation::$MORPH_CHOICE] &&
                $this->validation->items[EPValidation::$MOTIVATION_THREE_CHOICE] &&
                $this->validation->items[EPValidation::$ACTIVE_SKILLS_MIN] &&
                $this->validation->items[EPValidation::$KNOWLEDGE_SKILLS_MIN] &&
                $this->validation->items[EPValidation::$CREDIT_AMOUNT_ENOUGH];
    }

    /**
     * Get all the traits for either the current morph or the ego
     * @param bool $morph
     * @return EPTrait[]
     */
    function getCurrentTraits(bool $morph = false): array
    {
        if ($morph) {
            return $this->getCurrentMorph()->getTraits();
        }
        return $this->character->ego->getTraits();
    }

    // All the traits a morph has on it by default
    //
    // AKA, all the traits that can't be removed from the morph.
    function getCurrentDefaultMorphTraits($morph){
        return $morph->traits;
    }

    // All the traits granted by background and faction
    //
    // AKA all the traits that can only be removed by changing background or faction
    function getCurrentDefaultEgoTraits(){
        return $this->character->ego->traits;
    }

    // All possible traits
    //
    // Generated from database at session start, not saved in a file.
    // This means trait UIDs will change between sessions!
    function getTraits(){
        return $this->traits;
    }

    // Get all traits a morph has on it (both default and user generated)
    function getCurrentMorphTraits($morphName){
        $m = $this->getCurrentMorphsByName($morphName);
        return $m->getTraits();
    }

    function getAptitudePoint(){
        return $this->aptitudePoints;
    }

    // All the gear the morph has on it by default
    //
    // AKA, all the gear that can't be removed from the morph.
    function getCurrentDefaultMorphGear($morph){
        return $morph->gears;
    }

    // All the gear the morph has (both default and user generated)
    function getCurrentMorphGears($morphName){
        $m = $this->getCurrentMorphsByName($morphName);
        return $m->getGear();
    }

    function getGearForCurrentMorph(){
        $m = $this->getCurrentMorph();
        return $m->getGear();
    }

    function getGearForMorphName($morphName){
        $m = $this->getMorphByName($morphName);
        return $m->getGear();
    }

    function getCurrentMorphsByName($name): EPMorph
    {
        return EPAtom::getAtomByName($this->character->morphs,$name);
    }
    function getTraitByName($name): EPTrait
    {
        return EPAtom::getAtomByName($this->traits,$name);
    }

    /**
     * TODO:  Fixup by using EPAtom functions
     * @param string $psiName
     * @return bool
     */
    function havePsiSleight(string $psiName): bool
     {
        if (is_array($this->character->ego->psySleights)){
            foreach ($this->character->ego->psySleights as $p){
                if (strcmp($p->name, $psiName) == 0){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * If the morph has the trait (includes default and user added traits)
     * @param EPTrait $trait
     * @param EPMorph $morph
     * @return bool
     */
    function haveTraitOnMorph(EPTrait $trait, EPMorph$morph): bool{
        return $trait->isInArray($morph->getTraits());
    }

    /**
     * If the morph has a particular piece of gear on it (includes default and user added gear)
     * @param EPGear  $gear
     * @param EPMorph $morph
     * @return bool
     */
    function haveGearOnMorph(EPGear $gear, EPMorph $morph){
        return $gear->isInArray($morph->getGear());
    }

    function removeAI(EPAi $ai){
        if ($this->creationMode){
            if ($ai->isInArray($this->character->ego->defaultAis)){
                return true;
            }
            if (!$ai->isInArray($this->character->ego->ais)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character do not have this AI !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $ai->removeFromArray($this->character->ego->ais);
            $this->adjustAll();
            return true;
        }else{
            if ($ai->isInArray($this->character->ego->defaultAis)){
                return true;
            }
            if (!$ai->isInArray($this->character->ego->ais)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character do not have this AI !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $ai->removeFromArray($this->character->ego->ais);
            $this->evoCrePoint += $ai->getCost() * $ai->occurence;
            $this->adjustAll();
            return true;
        }
    }
    function addGear($gear, &$morph){
        if ($this->creationMode){
            if (!isset($gear)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No gear !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!isset($morph)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No morph !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $this->listProvider->connect();
            $gearToAdd = $this->listProvider->getGearByName($gear->name);
            //Special Bonus/Malus Implant Reject
            if (!$morph->implantReject || strcmp($gear->gearType,  EPGear::$IMPLANT_GEAR) != 0){
                $gearToAdd->addToArray($morph->additionalGears);
            }else{
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Implant Rejection Level II !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            $this->adjustAll();
            return true;
        }else{
            if (!isset($gear)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No gear !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!isset($morph)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No morph !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $this->listProvider->connect();
            $gearToAdd = $this->listProvider->getGearByName($gear->name);
            //Special Bonus/Malus Implant Reject
            if (!$morph->implantReject || strcmp($gear->gearType,  EPGear::$IMPLANT_GEAR) != 0){
                $gearToAdd->addToArray($morph->additionalGears);
            }
            $this->evoCrePoint -= $gearToAdd->getCost();
            $this->adjustAll();
            return true;
        }
    }

    function addFreeGear(EPGear $gear, EPMorph &$morph){
        $gear->addToArray($morph->additionalGears);
        if (!$this->creationMode){
            $this->evoCrePoint -= $gear->getCost();
        }
        $this->adjustAll();
    }

    function getCurrentPsySleight(){
        return $this->character->ego->psySleights;
    }

    function removeGear(EPGear $gear,EPMorph &$morph): bool
    {
        if ($this->creationMode){
            if (!$morph->isInArray($this->character->morphs)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character do not have this morph !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!$gear->isInArray($morph->additionalGears)){
                if ($gear->isInArray($morph->gear)){
                    array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This gear is a native morph gear, impossible to remove !)', EPCreatorErrors::$RULE_ERROR));
                    return false;
                }
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This morph do not have this additional gear !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $gear->removeFromArray($morph->additionalGears);
            $this->adjustAll();
            return true;
        }else{
            if (!$morph->isInArray($this->character->morphs)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character do not have this morph !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!$gear->isInArray($morph->additionalGears)){
                if ($gear->isInArray($morph->gear)){
                    array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This gear is a native morph gear, impossible to remove !)', EPCreatorErrors::$RULE_ERROR));
                    return false;
                }
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This morph do not have this additional gear !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $gear->removeFromArray($morph->additionalGears);
            $this->evoCrePoint += $gear->getCost() * $gear->occurence;
            $this->adjustAll();
            return true;
        }
    }
    function haveAdditionalGear(EPGear $gear, EPMorph $morph): bool
    {
        return $gear->isInArray($morph->additionalGears);
    }

    function addMorphCreationMode(EPMorph $morph): bool
    {
        if ($morph->addToArray($this->character->morphs)){
            $this->activateMorph($morph);
            $this->adjustAll();
            return true;
        }
        return false;
    }

    function addMorphUpdateMode(EPMorph $morph): bool
    {
        if ($morph->addToArray($this->character->morphs)){
            $this->evoCrePoint -= $morph->getCost();
            $this->activateMorph($morph);
            $this->adjustAll();
            return true;
        }
        return false;
    }

    function addMorph(EPMorph $morph)
    {
        if ($this->creationMode){
            $morph->buyInCreationMode = true;
            return $this->addMorphCreationMode($morph);
        }else{
            $morph->buyInCreationMode = false;
            return $this->addMorphUpdateMode($morph);
        }
    }
    function removeMorphCreationMode(EPMorph $morph)
    {
        if (is_array($this->character->morphs)){
            foreach ($this->character->morphs as $m){
                if (strcmp($m->name,$morph->name) == 0){
                    $cm = $this->getCurrentMorph();
                    if (isset($cm)){
                        if (strcmp($morph->name,$cm->name) == 0){
                            $this->activateMorph(null);
                        }
                    }
                    $list = array();
                    foreach ($morph->additionalTraits as $t){
                        array_push($list, $t);
                    }
                    foreach ($list as $t){
                        $this->removeTrait($t, $morph);
                    }
                    $list = array();
                    foreach ($morph->additionalGears as $g){
                        array_push($list, $g);
                    }
                    foreach ($list as $g){
                        $this->removeGear($g, $morph);
                    }
                    $morph->removeFromArray($this->character->morphs);
                    $this->adjustAll();
                    return true;
                }
            }
        }

        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Morph not exist in character morph list !)', EPCreatorErrors::$SYSTEM_ERROR));
        return false;
    }

    function removeMorphUpdateMode(EPMorph $morph): bool
    {
        if (is_array($this->character->morphs)){
            foreach ($this->character->morphs as $m){
                if (strcmp($m->name,$morph->name) == 0){
                    $cm = $this->getCurrentMorph();
                    if (isset($cm)){
                        if (strcmp($morph->name,$cm->name) == 0){
                            $this->activateMorph(null);
                        }
                    }
                    $list = array();
                    foreach ($morph->additionalTraits as $t){
                        array_push($list, $t);
                    }
                    foreach ($list as $t){
                        $this->removeTrait($t, $morph);
                    }
                    $list = array();
                    foreach ($morph->additionalGears as $g){
                        array_push($list, $g);
                    }
                    foreach ($list as $g){
                        $this->removeGear($g, $morph);
                    }
                    $this->evoCrePoint += $morph->getCost();
                    $morph->removeFromArray($this->character->morphs);
                    $this->adjustAll();
                    return true;
                }
            }
        }

        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Morph not exist in character morph list !)', EPCreatorErrors::$SYSTEM_ERROR));
        return false;
    }

    function removeMorph(EPMorph $morph): bool
    {
        if ($this->creationMode){
            return $this->removeMorphCreationMode($morph);
        }else{
            return $this->removeMorphUpdateMode($morph);
        }
    }

    //TODO:  Half of this should be in EPSkill
    function addSpecialization($name, EPSkill $skill): bool
    {
        if (!empty($skill->specialization)){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This skill already has a specialization!)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        if ($this->creationMode){
            $this->evoCrePoint -= $this->configValues->getValue('RulesValues','SpecializationCost');
        }else{
            $this->evoRezPoint -= $this->configValues->getValue('RulesValues','SpecializationCost');
        }
        $skill->specialization = $name;
        return true;
    }

    //TODO:  Half of this should be in EPSkill
    function removeSpecialization(EPSkill $skill): bool
    {
        if (empty($skill->specialization)){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No specialization to remove!)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        if ($this->creationMode){
            $this->evoCrePoint += $this->configValues->getValue('RulesValues','SpecializationCost');
        }else{
            $this->evoRezPoint += $this->configValues->getValue('RulesValues','SpecializationCost');
        }
        $skill->specialization = '';
        return true;
    }

    function addSoftGear(EPGear $softGear): bool
    {
        $result = $softGear->addToArray($this->character->ego->softGears);
        $this->adjustAll();
        return $result;
    }

    function removeSoftGear(EPGear $softGear): bool
    {
        if (!$softGear->removeFromArray($this->character->ego->softGears)) {
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Soft gear not exist in character softGear list !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        $this->adjustAll();
        return true;
    }

    function addMotivation(string $motivation)
    {
        array_push($this->character->ego->motivations, $motivation);
    }
    function removeMotivation($motiv){

		$candidat = array();
		foreach($this->character->ego->motivations as $m){
			if($m != $motiv) array_push($candidat, $m);
		}
		$this->character->ego->motivations = $candidat;
        return true;
    }
    function addTrait(EPTrait $trait, EPMorph $morph = null): bool
    {
        //Error checking
        if (isset($morph)){
            if (!$morph->isInArray($this->character->morphs)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character do not have this morph !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if ($this->haveTraitOnMorph($trait,$morph)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' ((This character morph own already this trait !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if ($trait->isEgo()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No ego trait on morph !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
        }else{
            if ($trait->isInArray( $this->character->ego->getTraits() )){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character ego own already this trait !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if ($trait->isMorph()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No morph trait on ego !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
        }

        if ($this->creationMode){
            //More error checking
            if ($trait->isPositive()){
                $totPosTrait = $this->getSumPosTraits();
                if ($totPosTrait + $trait->cpCost > $this->configValues->getValue('RulesValues','MaxPointPositiveTrait')){
                    array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max Positive Trait CP outdated !)', EPCreatorErrors::$RULE_ERROR));
                    return false;
                }
            }else{
                $totNegTrait = $this->getSumNegTraits();
                if ($totNegTrait + $trait->cpCost > $this->configValues->getValue('RulesValues','MaxPointNegativeTrait')){
                    array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max Negative Trait CP outdated !)', EPCreatorErrors::$RULE_ERROR));
                    return false;
                }
            }
            if (isset($morph)){
                if ($trait->isNegative()){
                    $totNegTrait = $this->getSumNegMorphTraits();
                    if ($totNegTrait + $trait->cpCost > $this->configValues->getValue('RulesValues','MaxPointNegativeTraitOnMorph')){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max Negative Trait CP for morphs outdated !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                }
                if($this->isLowerLevelBuy($trait,$morph->traits) || $this->isLowerLevelBuy($trait,$morph->additionalTraits)){
                    $this->sellLowerLevel($trait,$morph);
                }
                if($this->isHigherLevelBuy($trait,$morph->traits) || $this->isHigherLevelBuy($trait,$morph->additionalTraits)){
                    $this->sellHigherLevel($trait,$morph);
                }

                array_push($morph->additionalTraits,$trait);
            }else{
                if($this->isLowerLevelBuy($trait,$this->character->ego->traits) || $this->isLowerLevelBuy($trait,$this->character->ego->additionalTraits)){
                        $this->sellLowerLevel($trait,null);
                }
                if($this->isHigherLevelBuy($trait,$this->character->ego->traits) || $this->isHigherLevelBuy($trait,$this->character->ego->additionalTraits)){
                        $this->sellHigherLevel($trait,null);
                }

                array_push($this->character->ego->additionalTraits,$trait);
            }

            $this->adjustAll();
            return true;
        }else{
            if (isset($morph)){
                $listOldTraits = $this->back->getCurrentTraits(true);
                $haveOld = $trait->isInArray($listOldTraits);

                if($this->isLowerLevelBuy($trait,$morph->traits) || $this->isLowerLevelBuy($trait,$morph->additionalTraits)){
                    $this->sellLowerLevel($trait,$morph);
                }
                if($this->isHigherLevelBuy($trait,$morph->traits) || $this->isHigherLevelBuy($trait,$morph->additionalTraits)){
                    $this->sellHigherLevel($trait,$morph);
                }

                $this->listProvider->connect();
                $traitToAdd = $this->listProvider->getTraitByName($trait->name);
                $traitToAdd->addToArray($morph->additionalTraits);
            }else{
                $listOldTraits = $this->back->getCurrentTraits(false);
                $haveOld = $trait->isInArray($listOldTraits);

                if($this->isLowerLevelBuy($trait,$this->character->ego->traits) || $this->isLowerLevelBuy($trait,$this->character->ego->additionalTraits)){
                        $this->sellLowerLevel($trait,null);
                }
                if($this->isHigherLevelBuy($trait,$this->character->ego->traits) || $this->isHigherLevelBuy($trait,$this->character->ego->additionalTraits)){
                        $this->sellHigherLevel($trait,null);
                }

                array_push($this->character->ego->additionalTraits,$trait);
            }

            if (!$trait->isNegative()){
                if (!$haveOld){
                    $this->evoRezPoint -= $trait->cpCost;
                }
            }

            $this->adjustAll();
            return true;
        }
    }
    function removeTrait(EPTrait $trait,?EPMorph $morph = null): bool
    {
        //Error checking
        if (isset($morph)){
            if (!$morph->isInArray($this->character->morphs)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character do not have this morph !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!$this->haveTraitOnMorph($trait,$morph)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This morph do not have this trait !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!$trait->isInArray($morph->additionalTraits)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Can not remove default morph traits !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
        }else{
            if (!$trait->isInArray( $this->character->ego->getTraits() )){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego do not have this trait !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if (!$trait->isInArray($this->character->ego->additionalTraits)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Can not remove background/faction traits !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
        }

        if ($this->creationMode){
            if (isset($morph)){
                $trait->removeFromArray($morph->additionalTraits);
            }else{
                $trait->removeFromArray($this->character->ego->additionalTraits);
            }
        }else{
            if (isset($morph)){
                $listOldTraits = $this->back->getCurrentTraits(true);
                $haveOld = $trait->isInArray($listOldTraits);
                $trait->removeFromArray($morph->additionalTraits);
            }else{
                $listOldTraits = $this->back->getCurrentTraits(false);
                $haveOld = $trait->isInArray($listOldTraits);
                $trait->removeFromArray($this->character->ego->additionalTraits);
            }
            if (!$trait->isNegative()){
                if (!$haveOld){
                    $this->evoRezPoint += $trait->cpCost;
                }
            }
        }
        $this->adjustAll();
        return true;
    }
    function isLowerLevelBuy($trait,$currentTraits){
	$traitName = $this->removeLastWord($trait->name);
        foreach ($currentTraits as $t){
            if (strcmp($this->removeLastWord($t->name), $traitName) == 0 &&
                    $trait->level > $t->level){
                return true;
            }
        }

        return false;
    }
    function isHigherLevelBuy($trait,$currentTraits){

	    $traitName = $this->removeLastWord($trait->name);
	    foreach ($currentTraits as $t){
	        if (strcmp($this->removeLastWord($t->name), $traitName) == 0 &&
	        	$trait->level < $t->level){
	            return true;
	        }
	    }

        return false;
    }

    function sellLowerLevel(EPTrait $trait, ?EPMorph $morph = null)
    {
    	if(isset($morph)){
    		$traitName = $this->removeLastWord($trait->name);
		    foreach ($morph->additionalTraits as $t){
		        if (strcmp($this->removeLastWord($t->name), $traitName) == 0 &&
		        	$t->level < $trait->level){
		            	$this->removeTrait($t,$morph);
		            	break;
		        }
		    }
    	}
    	else{
	    	$traitName = $this->removeLastWord($trait->name);
		    foreach ($this->character->ego->additionalTraits as $t){
		        if (strcmp($this->removeLastWord($t->name), $traitName) == 0 &&
		        	$t->level < $trait->level){
		            	$this->removeTrait($t,null);
		            	break;
		        }
		    }
    	}

    }

    function sellHigherLevel(EPTrait $trait, ?EPMorph $morph = null)
    {
    	if(isset($morph)){
    		$traitName = $this->removeLastWord($trait->name);
		    foreach ($morph->additionalTraits as $t){
		        if (strcmp($this->removeLastWord($t->name), $traitName) == 0 &&
		        	$t->level > $trait->level){
		            	$this->removeTrait($t,$morph);
		            	break;
		        }
		    }
    	}
    	else{
	    	$traitName = $this->removeLastWord($trait->name);
		    foreach ($this->character->ego->additionalTraits as $t){
		        if (strcmp($this->removeLastWord($t->name), $traitName) == 0 &&
		        	$t->level > $trait->level){
		            	$this->removeTrait($t,null);
		            	break;
		        }
		    }
    	}

    }

    function addPsySleight(EPPsySleight $psySleight)
    {
        if ($this->creationMode){
            if ($this->havePsiSleight($psySleight->name)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character ego own already this psySleight !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            array_push($this->character->ego->psySleights,$psySleight);
            $this->adjustAll();

            return true;
        }else{
            if ($this->havePsiSleight($psySleight->name)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character ego own already this psySleight !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $psySleight->buyinCreationMode = false;
            array_push($this->character->ego->psySleights,$psySleight);
            $this->evoRezPoint -= $this->configValues->getValue('RulesValues','PsyCpCost');
            return true;
        }
    }

    function removePsySleight(EPPsySleight $psySleight)
    {
        if ($this->creationMode){
            if (!$this->havePsiSleight($psySleight->name)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego do not have this trait !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $psySleight->removeFromArray($this->character->ego->psySleights);
            $this->adjustAll();

            return true;
        }else{
            if (!$this->havePsiSleight($psySleight->name)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego do not have this trait !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            $psySleight->removeFromArray($this->character->ego->psySleights);
            if (!$psySleight->buyinCreationMode){
                $this->evoRezPoint += $this->configValues->getValue('RulesValues','PsyCpCost');
            }
        }
    }

    // Create a skill from a user entered name and pre-defined prefix
    function addSkill($name, $linkedApt, $skillType, $defaultable, $prefix = '', $groups = null,$nativeLanguage = false){
        if (!$this->prefixExist($prefix)){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Prefix not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        $ns = new EPSkill($name,
                          '',
                          $this->getAptitudeByAbbreviation($linkedApt),
                          $skillType,
                          $defaultable,
                          $prefix,
                          $groups,
                          0,
                          true
                          );
        if (!$ns->addToArray($this->character->ego->skills)){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Skill already exist !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        if($nativeLanguage){
            $ns->isNativeTongue = true;
            $ns->nativeTongueBonus = $this->configValues->getValue('RulesValues','NativeTongueBaseValue');
            $this->nativeLanguageSet = true;
        }
        $this->adjustAll();
        return true;
    }

    function removeSkill(EPSkill $skill): bool
    {
        if ($skill->tempSkill === false){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Impossible to remove a permanent skill !)', EPCreatorErrors::$RULE_ERROR));
            return false;
        }
        if($skill->isNativeTongue) $this->nativeLanguageSet = false;
        if($skill->removeFromArray($this->character->ego->skills)){
            return true;
        }
        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This Skill not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
        return false;
    }
    function clearErrorList(){
        $this->errorList = array();
        return true;
    }
    function getActiveRestNeed(){
        $need = $this->configValues->getValue('RulesValues','ActiveSkillsMinimum');
        foreach ($this->character->ego->skills as $sk){
            if ($sk->skillType == EPSkill::$ACTIVE_SKILL_TYPE){
                $need -= $this->getRealCPCostForSkill($sk);
            }
        }
        return max(0,$need);
    }

    function getCurrentMorph(): ?EPMorph
    {
        return EPAtom::getAtomByUid($this->character->morphs, $this->character->currentMorphUid);
    }
    function getCredit(){
        if ($this->creationMode){
            $this->adjustCredit();
            return $this->character->ego->creditInstant;
        }else{
            return $this->evoCrePoint + $this->evoCrePointPurchased;
        }
    }

    function getCurrentBackground(): ?EPBackground
    {
        return $this->character->ego->background ?? null;
    }

    function getCurrentFaction(): ?EPBackground
    {
        return $this->character->ego->faction ?? null;
    }
    function getErrorList(){
        return $this->errorList;
    }
    function getGears(){
        return $this->gears;
    }

    function getAis(){        
        return $this->ais;
    }

    function getAisByName($name){
        return EPAtom::getAtomByName($this->ais,$name);
    }

    function haveAi(EPAi $ai): bool
    {
        return $ai->isInArray($this->character->ego->ais);
    }

    function getGearByName($name){
        return EPAtom::getAtomByName($this->gears,$name);
    }

    /**
     * @return EPGear[]
     */
    function getEgoSoftGears(): array
    {
        return $this->character->ego->softGears;
    }

    function haveSoftGear(EPGear $soft): bool
    {
        return $soft->isInArray($this->character->ego->softGears);
    }

    /**
     * @return EPAi[]
     */
    function getEgoAi(): array
    {
        $res = array();
        $res = array_merge($res,$this->character->ego->ais);
        $res = array_merge($res,$this->character->ego->defaultAis);
        return $res;
    }

    function getDefaultEgoAi(){
	    return $this->character->ego->defaultAis;
    }

    function getKnowledgeRestNeed(){
        $need = $this->configValues->getValue('RulesValues','KnowledgeSkillsMinimum');
        foreach ($this->character->ego->skills as $sk){
            if ($sk->skillType == EPSkill::$KNOWLEDGE_SKILL_TYPE){
                $need -= $this->getRealCPCostForSkill($sk);
            }
        }
        return max(0,$need);
    }

    function getAiAptitudeByAbbreviation($ai,$abbrev){
        foreach ($ai->aptitudes as $a){
            if (strcmp($a->abbreviation,$abbrev) == 0){
                return $a;
            }
        }
        return null;
    }
    function getAptitudeByAbbreviation($abbrev){
        foreach ($this->character->ego->aptitudes as $a){
            if (strcmp($a->abbreviation,$abbrev) == 0){
                return $a;
            }
        }
        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This aptitude not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
        return null;
    }
    function getAptitudes(){
        return $this->character->ego->aptitudes;
    }
    function getBackgroundByName($name){
        $ret = EPAtom::getAtomByName($this->backgrounds,$name);
        if($ret == null){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This background not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
        }
        return $ret;
    }
    function getBackgrounds(){
        return $this->backgrounds;
    }
    function getBonusMalus($activeMorphOnly = true){
        $res = array();

        if ($activeMorphOnly === true){
            $m = $this->getCurrentMorph();
            if (isset($m)){
                $res = array_merge($res, $m->bonusMalus);
                foreach ($m->traits as $t){
                   $res =  array_merge($res, $t->bonusMalus);
                }
                foreach ($m->additionalTraits as $at){
                   $res =  array_merge($res, $at->bonusMalus);
                }
                foreach ($m->gears as $g){
                    $res = array_merge($res, $g->bonusMalus);
                }
                foreach ($m->additionalGears as $g){
                    $res = array_merge($res, $g->bonusMalus);
                }
            }
        }else{
            foreach ($this->character->morphs as $m){
                $res = array_merge($res, $m->bonusMalus);
                foreach ($m->traits as $t){
                    $res = array_merge($res, $t->bonusMalus);
                }
                foreach ($m->additionalTraits as $at){
                   $res =  array_merge($res, $at->bonusMalus);
                }
                foreach ($m->gears as $g){
                   $res =  array_merge($res, $g->bonusMalus);
                }
                foreach ($m->additionalGears as $g){
                   $res =  array_merge($res, $g->bonusMalus);
                }
            }
        }

        foreach ($this->character->ego->traits as $t){
            $res = array_merge($res, $t->bonusMalus);
        }
        foreach ($this->character->ego->additionalTraits as $at){
            $res =  array_merge($res, $at->bonusMalus);
        }
        foreach ($this->character->ego->softGears as $g){
            $res = array_merge($res, $g->bonusMalus);
        }
        foreach ($this->character->ego->ais as $a){
           $res =  array_merge($res, $a->bonusMalus);
        }
        foreach ($this->character->ego->defaultAis as $da){
           $res =  array_merge($res, $da->bonusMalus);
        }
        foreach ($this->character->ego->psySleights as $p){
            $res = array_merge($res, $p->bonusMalus);
        }
        if (isset($this->character->ego->faction)){
            $res = array_merge($res, $this->character->ego->faction->bonusMalus);
        }
        if (isset($this->character->ego->background)){
            $res = array_merge($res, $this->character->ego->background->bonusMalus);
        }

        return $res;
    }

    function getBonusMalusForMorph($m){
    	$res = array();
		$res = array_merge($res, $m->bonusMalus);
        foreach ($m->traits as $t){
           $res =  array_merge($res, $t->bonusMalus);
        }
        foreach ($m->additionalTraits as $at){
           $res =  array_merge($res, $at->bonusMalus);
        }
        foreach ($m->gears as $g){
            $res = array_merge($res, $g->bonusMalus);
        }
        foreach ($m->additionalGears as $g){
            $res = array_merge($res, $g->bonusMalus);
        }
        return $res;
    }

    function getBonusMalusEgo(){
    	$res = array();
    	foreach ($this->character->ego->traits as $t){
            $res = array_merge($res, $t->bonusMalus);
        }
        foreach ($this->character->ego->additionalTraits as $at){
            $res =  array_merge($res, $at->bonusMalus);
        }
        foreach ($this->character->ego->softGears as $g){
            $res = array_merge($res, $g->bonusMalus);
        }
        foreach ($this->character->ego->ais as $a){
            $res = array_merge($res, $a->bonusMalus);
        }
        foreach ($this->character->ego->defaultAis as $da){
           $res =  array_merge($res, $da->bonusMalus);
        }
        foreach ($this->character->ego->psySleights as $p){
            $res = array_merge($res, $p->bonusMalus);
        }
        if (isset($this->character->ego->faction)){
            $res = array_merge($res, $this->character->ego->faction->bonusMalus);
        }
        if (isset($this->character->ego->background)){
            $res = array_merge($res, $this->character->ego->background->bonusMalus);
        }
        return $res;
    }

    function getLastError(){
        $nbError = count($this->errorList);

        if ($nbError > 0){
            $res = $this->errorList[$nbError-1];
            array_pop($this->errorList);
            return $res;
        }

        return '';
    }
    function getSkillsByPrefix($prefix){
        $res = array();
        foreach ($this->character->ego->skills as $sk){
            if (strcmp($sk->prefix,$prefix) == 0){
                array_push($res, $sk);
            }
        }
        return $res;
    }
    //WARNING / FIXME:  Dangerous (Skills should always be referenced by name AND prefix)
    function getAiSkillByName($ai,$name){
        return EPAtom::getAtomByName($ai->skills,$name);
    }
    function getSkillByAtomUid($id){
        $ret = EPAtom::getAtomByUid($this->character->ego->skills,$id);
        if($ret == null){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This skill not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
        }
        return $ret;
    }
    function getSkillsByGroup($group){
        $res = array();
        foreach ($this->character->ego->skills as $sk){
            foreach ($sk->groups as $grp){
                if (strcmp($grp,$group) == 0){
                    array_push($res, $sk);
                }
            }
        }
        return $res;
    }
    function getSkillsRestNeed(){
        return $this->getActiveRestNeed() + $this->getKnowledgeRestNeed();
    }
    function getSkills(){
    	$res = $this->character->ego->skills;

    	usort($res, [EPSkill::class, 'compareSkillsByPrefixName']);

	    return $res;
    }
    function getMotivations(){
        return $this->character->ego->motivations;
    }

    function getMorphByName($name){
        return EPAtom::getAtomByName($this->morphs,$name);
    }

    // All possible morphs
    //
    // This is generated from the database at session creation, so UIDs will vary from session to session.
    function getMorphs(){
        return $this->morphs;
    }

    // All morphs the character has
    function getCurrentMorphs(){
        return $this->character->morphs;
    }

    function getStats(){
	    return $this->character->ego->stats;
    }
    function getStatByName($name){
        $ret = EPAtom::getAtomByName($this->character->ego->stats,$name);
        if($ret == null){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This stat not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
        }
        return $ret;
    }
    function getStatByAbbreviation($abbr){
        foreach ($this->character->ego->stats as $s){
            if (strcmp($s->abbreviation,$abbr) == 0){
                return $s;
            }
        }
        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This stat not exist ! ('.$abbr.'))', EPCreatorErrors::$SYSTEM_ERROR));
        return null;
    }
    function setAiAptitudeValue($ia,$abbreviation, $newValue){
        $apt = $this->getAiAptitudeByAbbreviation($ia,$abbreviation);
        if (!isset($apt)){
            return false;
        }
        if ($newValue == $apt->value){
            return true;
        }
        $apt->value = $newValue;
        return true;
    }
    function setAptitudeValue($abreviation, $newValue){
        if ($this->creationMode){
            $apt = $this->getAptitudeByAbbreviation($abreviation);

            if (!isset($apt)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No aptitude with this abbreviation !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if ($newValue == $apt->value){
                return true;
            }
            if ($apt->feebleMax && $newValue > 4){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level(1) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if ($newValue > $apt->getMaxEgoValue()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level(2) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if (!$apt->feebleMax && $newValue < $apt->getMinEgoValue()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level(3) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if ($apt->feebleMax && $newValue < 0){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level(3) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }

            $apt->value = $newValue;
            $this->aptitudePoints = max(0,$this->configValues->getValue('RulesValues','AptitudesPoint')-$this->getSumAptitudes());
            $this->adjustValues();
            return true;
        }else{
            $apt = $this->getAptitudeByAbbreviation($abreviation);
            if (!isset($apt)){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No aptitude with this abbreviation !)', EPCreatorErrors::$SYSTEM_ERROR));
                return false;
            }
            if ($apt->feebleMax && $newValue > 4){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level(e1) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if ($newValue > $apt->getMaxEgoValue()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level(e2) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if (!$apt->feebleMax && $newValue < $apt->getMinEgoValue()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level(e3) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if ($apt->feebleMax && $newValue < 0){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level(e4) for this aptitude outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }

            $oldApt = $this->back->getAptitudeByAbbreviation($apt->abbreviation);
            $diff = $newValue - $apt->value;
            if ($newValue < $oldApt->value){
                $this->evoRezPoint += max(0,$apt->value - $oldApt->value) * $this->configValues->getValue('RulesValues','AptitudePointCost');
                $apt->value = $newValue;
                $this->checkSkillsForChangeAptitudeValue($apt,$diff);
                return true;
            }else{
                $apt->value = max($apt->value,$oldApt->value);
                $this->evoRezPoint -= ($newValue - $apt->value) * $this->configValues->getValue('RulesValues','AptitudePointCost');
                $apt->value = $newValue;
                $this->checkSkillsForChangeAptitudeValue($apt,$diff);
                return true;
            }
        }
    }
    function checkSkillsForChangeAptitudeValue($apt,$diff){
        if ($diff == 0) return;

        if ($diff > 0){
            foreach ($this->character->ego->skills as $sk) {
                if (strcmp($sk->linkedApt->abbreviation,$apt->abbreviation) == 0){
                    $up = ($sk->baseValue + $sk->getBonusForCost()) - $this->configValues->getValue('RulesValues','SkillLimitForImprove');
                    $this->evoRezPoint -= max(0,min($up,$diff));
                }
            }
        }else{
            foreach ($this->character->ego->skills as $sk) {
                if (strcmp($sk->linkedApt->abbreviation,$apt->abbreviation) == 0){
                    $t = max(0,$this->configValues->getValue('RulesValues','SkillLimitForImprove') - ($sk->baseValue + $sk->getBonusForCost()));
                    $t = max(0,-$diff - $t);
                    $this->evoRezPoint += $t;
                }
            }
        }
    }
    function setBackground($background){
        if ($this->creationMode){
            $this->character->ego->background = $background;
            $this->setEgoTraits();
            $this->adjustAll();
            return true;
        }else{
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Evolution mode no background change !)', EPCreatorErrors::$RULE_ERROR));
            return false;
        }
    }
    function setFaction($faction){
        if ($this->creationMode){
            $this->character->ego->faction = $faction;
            $this->setEgoTraits();
            $this->adjustAll();
            return true;
        }else{
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Evolution mode no faction change  !)', EPCreatorErrors::$RULE_ERROR));
            return false;
        }
    }

    // Set the ego traits from background and faction
    function setEgoTraits(){
        $background_traits = array();
        $faction_traits = array();
        if (!empty($this->character->ego->background) && is_array($this->character->ego->background->traits)){
            $background_traits = $this->character->ego->background->traits;
        }
        if (!empty($this->character->ego->faction) && is_array($this->character->ego->faction->traits)){
            $faction_traits = $this->character->ego->faction->traits;
        }
        $this->character->ego->traits = array_merge($background_traits,$faction_traits);
    }

    function setOccurenceGear( $gearName, $occurence,$morphName = null){
      if ($occurence < 1){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Minimum 1 !)', EPCreatorErrors::$RULE_ERROR));
            return false;
      }

      if ($this->creationMode){
        if (isset($morphName)){
          foreach ($this->character->morphs as $m){
            if (strcmp($m->name, $morphName) == 0){
              foreach ($m->gears as $g){
                if (strcmp($g->name, $gearName) == 0){
                  $g->occurence = $occurence;
                  return true;
                }
              }
              foreach ($m->additionalGears as $g){
                if (strcmp($g->name, $gearName) == 0){
                  $g->occurence = $occurence;
                  return true;
                }
              }
              array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This morph not have this gear  !)', EPCreatorErrors::$RULE_ERROR));
              return false;
            }
          }
          array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character not have this morph  !)', EPCreatorErrors::$RULE_ERROR));
          return false;
        }else{
          foreach ($this->character->ego->softGears as $sg){
            if (strcmp($sg->name, $gearName) == 0){
              $sg->occurence = $occurence;
              return true;
            }
          }
          array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego not have this gear !)', EPCreatorErrors::$RULE_ERROR));
          return false;
        }
      }else{
        if (isset($morphName)){
          foreach ($this->character->morphs as $m){
            if (strcmp($m->name, $morphName) == 0){
              foreach ($m->gears as $g){
                if (strcmp($g->name, $gearName) == 0){
                  $this->evoCrePoint -= ($occurence - $g->occurence) * $g->getCost();
                  $g->occurence = $occurence;
                  return true;
                }
              }
              foreach ($m->additionalGears as $g){
                if (strcmp($g->name, $gearName) == 0){
                  $this->evoCrePoint -= ($occurence - $g->occurence) * $g->getCost();
                  $g->occurence = $occurence;
                  return true;
                }
              }
              array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This morph not have this gear  !)', EPCreatorErrors::$RULE_ERROR));
              return false;
            }
          }
          array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This character not have this morph  !)', EPCreatorErrors::$RULE_ERROR));
          return false;
        }else{
          foreach ($this->character->ego->softGears as $sg){
            if (strcmp($sg->name, $gearName) == 0){
              $this->evoCrePoint -= ($occurence - $sg->occurence) * $sg->getCost();
              $sg->occurence = $occurence;
              return true;
            }
          }
          array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego not have this gear !)', EPCreatorErrors::$RULE_ERROR));
          return false;
        }
      }

    }
    function setOccurenceIA($iaName,$occurence){
      if ($occurence < 1){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Minimum 1 !)', EPCreatorErrors::$RULE_ERROR));
            return false;
      }

      if ($this->creationMode){
        foreach ($this->character->ego->ais as $a){
          if (strcmp($a->name, $iaName) == 0){
            $a->occurence = $occurence;
            return true;
          }
        }
        foreach ($this->character->ego->defaultAis as $a){
          if (strcmp($a->name, $iaName) == 0){
            $a->occurence = $occurence;
            return true;
          }
        }
        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego not have this ia !)', EPCreatorErrors::$RULE_ERROR));
        return false;
      }else{
        foreach ($this->character->ego->ais as $a){
          if (strcmp($a->name, $iaName) == 0){
            $this->evoCrePoint -= ($occurence - $a->occurence) * $a->getCost();
            $a->occurence = $occurence;
            return true;
          }
        }
        foreach ($this->character->ego->defaultAis as $a){
          if (strcmp($a->name, $iaName) == 0){
            $this->evoCrePoint -= ($occurence - $a->occurence) * $a->getCost();
            $a->occurence = $occurence;
            return true;
          }
        }
        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This ego not have this ia !)', EPCreatorErrors::$RULE_ERROR));
        return false;
      }
    }

    // Set the new reputation value
    //
    // Note:  This does allow players to remove reputation from a different faction, and gain them back as rep points
    function setReputation($name,$newValue){
        $rep = $this->getReputationByName($name);

        if (!isset($rep)){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Reputation not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        if ($newValue > $rep->getMaxValue()){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level for this Reputation outdated !)', EPCreatorErrors::$RULE_ERROR));
            return false;
        }
        if ($newValue < 0){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level for this Reputation outdated !)', EPCreatorErrors::$RULE_ERROR));
            return false;
        }
        if ($rep->value == $newValue){
            return true;
        }


        if ($this->creationMode){
            if ($newValue > $rep->getAbsoluteValue()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level for this Reputation outdated !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
        }
        $rep->value = $newValue;
        return true;
    }
    function setMaxRepValue($newValue){
        foreach ($this->character->ego->reputations as $r) {
            $r->maxValue = $newValue;
        }
    }
    function setMaxSkillValue($newValue){
        foreach ($this->character->ego->skills as $s) {
            $s->maxValue = $newValue;
        }
    }
    function setAiSkillValue($ai,$name,$value = 0){
        $sk = $this->getAiSkillByName($ai,$name);

        if (!isset($sk)){
            return false;
        }
        if ($sk->baseValue == $value){
            return true;
        }
        $sk->baseValue = $value;
        return true;
    }
    function setSkillValue($id,$value = 0){
        $sk = $this->getSkillByAtomUid($id);
        if (!isset($sk)){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No skill with this id !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }
        if ($this->creationMode){
            if ($sk->baseValue == $value){
                return true;
            }
            if ($value + $sk->getBonusForCost() > $sk->getMaxValue()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level ('.$sk->getMaxValue().') outdated ('.$value.')('.$sk->getBonusForCost().') !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if ($value < 0){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Value less than 0 !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            $diffCost = $this->getDiffCost($sk,$value);
            if ($diffCost == 0){
                return true;
            }
            $sk->baseValue = $value;
            return true;
        }else{
            $diff = $value - $sk->baseValue;
            $oldSk = $this->back->getSkillByAtomUid($sk->getUid());
            if (empty($oldSk)){
                $oldSk = $sk;
            }

            while($diff != 0){
                if ($diff > 0){
                    if ($sk->baseValue + $sk->getBonusForCost() >= $sk->getMaxValue()){
                        $diff = 0;
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level ('.$sk->getMaxValue().') outdated ('.$value.')('.$sk->getBonusForCost().') !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }else{
                        if($sk->baseValue + $sk->getBonusForCost() >= $this->configValues->getValue('RulesValues','SkillLimitForImprove')){
                            $this->evoRezPoint -= $this->configValues->getValue('RulesValues','SkillPointUpperCost');
                            $sk->baseValue += 1;
                            $diff -= 1;
                        }else if ($sk->baseValue >= $oldSk->baseValue){
                            $this->evoRezPoint -= $this->configValues->getValue('RulesValues','SkillPointUnderCost');
                            $sk->baseValue += 1;
                            $diff -= 1;
                        }else{
                            $sk->baseValue += 1;
                            $diff -= 1;
                        }
                    }
                }else{
                    if ($sk->baseValue > $oldSk->baseValue){
                        if ($sk->baseValue + $sk->getBonusForCost() > $this->configValues->getValue('RulesValues','SkillLimitForImprove')){
                            $this->evoRezPoint += $this->configValues->getValue('RulesValues','SkillPointUpperCost');
                            $sk->baseValue -= 1;
                            $diff += 1;
                        }else{
                            $this->evoRezPoint += $this->configValues->getValue('RulesValues','SkillPointUnderCost');
                            $sk->baseValue -= 1;
                            $diff += 1;
                        }
                    }else{
                        if ($sk->baseValue > 0){
                            $sk->baseValue -= 1;
                            $diff += 1;
                        }else{
                            $diff = 0;
                            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Value less than 0 !)', EPCreatorErrors::$RULE_ERROR));
                            return false;
                        }
                    }
                }
            }
            return true;
        }
    }
    function setStat($name,$newValue){
        if ($this->creationMode){
            foreach ($this->character->ego->stats as $stat){
                if (strcmp($stat->abbreviation,$name) == 0){
                    if (strcmp($stat->abbreviation,EPStat::$MOXIE) != 0){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Stat not be changed !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                    if ($newValue == $stat->value){
                        return true;
                    }
                    if ($newValue < $this->configValues->getValue('RulesValues','MoxMinPoint')){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level for Mox outdated !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                    if ($newValue > $this->configValues->getValue('RulesValues','MoxMaxPoint')){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level for Mox outdated !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                    $diff = $newValue - $stat->value;
                    $need = $diff * $this->configValues->getValue('RulesValues','MoxiePointCost');
                    $stat->value = $newValue;
                    return true;
                }
            }
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Stat not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }else{
            foreach ($this->character->ego->stats as $stat){
                if (strcmp($stat->abbreviation,$name) == 0){
                    if (strcmp($stat->abbreviation,EPStat::$MOXIE) != 0){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Stat not be changed !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                    if ($newValue < $this->configValues->getValue('RulesValues','MoxMinPoint')){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Min level for Mox outdated !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                    if ($newValue > $this->configValues->getValue('RulesValues','MoxEvoMaxPoint')){
                        array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max level for Mox outdated !)', EPCreatorErrors::$RULE_ERROR));
                        return false;
                    }
                    $diff = $stat->value - $newValue;
                    $this->evoRezPoint += $diff * $this->configValues->getValue('RulesValues','MoxiePointCost');
                    $stat->value = $newValue;
                    return true;
                }
            }
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Stat not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
            return false;
        }

    }
    function isNativeLanguageSet(){
	    return $this->nativeLanguageSet;
    }
    function getCanPsyChi(){
    	foreach($this->character->ego->traits as $t){
    		if($t->name == EPCharacterCreator::$PSY_CHI_TRAIT_NAME || $t->name == EPCharacterCreator::$PSY_GAMMA_TRAIT_NAME) return true;
    	}
    	foreach($this->character->ego->additionalTraits as $t){
    		if($t->name == EPCharacterCreator::$PSY_CHI_TRAIT_NAME || $t->name == EPCharacterCreator::$PSY_GAMMA_TRAIT_NAME) return true;
    	}
	    return false;
    }
    function getCanPsyGamma(){
        foreach($this->character->ego->traits as $t){
            if(strpos($t->name,EPCharacterCreator::$PSY_GAMMA_TRAIT_NAME) !== false) return true;
        }
        foreach($this->character->ego->additionalTraits as $t){
            if(strpos($t->name,EPCharacterCreator::$PSY_GAMMA_TRAIT_NAME) !== false) return true;
        }
        return false;
    }
    private function init($pathToConfig,$amountCP){
        $this->listProvider = new EPListProvider($pathToConfig);
        $this->configValues = new EPConfigFile($pathToConfig);
        $this->errorList = array();
        $this->aptitudePoints = $this->configValues->getValue('RulesValues','AptitudesPoint');
        $this->reputationPoints = $this->configValues->getValue('RulesValues','RepStart');
        $this->reputationPointsMorphMod = 0;
        $this->reputationPointsTraitMod = 0;
        $this->reputationPointsFactionMod = 0;
        $this->reputationPointsBackgroundMod = 0;
        $this->reputationPointsSoftGearMod = 0;
        $this->reputationPointsPsyMod = 0;
        $this->character = new EPCharacter();
        $this->character->ego->credit = $this->configValues->getValue('RulesValues','CreditStart');
        $this->character->ego->rep = $this->configValues->getValue('RulesValues','RepStart');

        $this->loadAptitudes();
        $this->loadStats();
        $this->loadGroups();
        $this->loadSkills();
        $this->loadPrefixs();
        $this->loadReps();
        $this->loadBackgrounds();
        $this->loadMorphs();
        $this->loadAis();
        $this->loadGears();
        $this->loadTraits();
        $this->loadPsySleights();

        $defaultAi = $this->getAisByName('Standard Muse');
        if (isset($defaultAi)){
            $this->character->ego->addDefaultAi($defaultAi);
        }

        $this->nativeLanguageSet = false;

        $amountCP = intval($amountCP);
        if ($amountCP < 0 ){
            $amountCP = $this->configValues->getValue('RulesValues','CreationPoint');
        }
        $amountCP = max($amountCP,
                $this->configValues->getValue('RulesValues','ActiveSkillsMinimum') +
                $this->configValues->getValue('RulesValues','KnowledgeSkillsMinimum'));

    	$this->initialCreationPoints = $amountCP;

        $this->adjustAll();
    }
    function getRezPoints(){
        if ($this->creationMode){
            return 'N/A';
        }else{
            $rez = $this->evoRezPoint;
            $rez -= $this->getCostForReputation();
            $rez -= $this->evoCrePointPurchased * $this->configValues->getValue('RulesValues','CreditPointCost');


            return $rez;
        }
    }
    function getCreationPoint(){
        $ret =  $this->initialCreationPoints;

        $ret += $this->getCreationPointFromTrait();
        $ret -= $this->getCostForApts();
        $ret -= $this->getCostForStats();
        $ret -= $this->getCostForMorphs();
        $ret -= $this->getCostForReputation();
        $ret -= $this->getCostForSkills();
        $ret -= $this->getCostForPsysleights();
        $ret -= $this->character->ego->creditPurchased * $this->configValues->getValue('RulesValues','CreditPointCost');

        if ($this->creationMode){
            return $ret;
        }else{
            return 'N/A';
        }
    }
    function getCreationPointFromTrait(){
        $ret = 0;
        foreach ($this->character->ego->additionalTraits as $t) {
            if ($t->isPositive()){
                $ret -= $t->cpCost;
            }else{
                $ret += $t->cpCost;
            }
        }
        foreach ($this->character->morphs as $m) {
            foreach ($m->additionalTraits as $t) {
                if ($t->isPositive()){
                    $ret -= $t->cpCost;
                }else{
                    $ret += $t->cpCost;
                }
            }
        }
        return $ret;
    }
    function adjustStats(){
        $this->getStatByName(EPStat::$LUCIDITY)->value = $this->getAptitudeByAbbreviation(EPAptitude::$WILLPOWER)->getValue() * 2;
        $this->getStatByName(EPStat::$TRAUMATHRESHOLD)->value = round($this->getStatByName(EPStat::$LUCIDITY)->value / 5);
        $this->getStatByName(EPStat::$INSANITYRATING)->value =  $this->getStatByName(EPStat::$LUCIDITY)->value * 2;

        $morph = $this->getCurrentMorph();
        if (isset($morph)){
            $this->getStatByName(EPStat::$DURABILITY)->value = $morph->durability;

            if ($morph->morphType != EPMorph::$SYNTHMORPH){
            	//error_log("DR 1.5");
                $this->getStatByName(EPStat::$DEATHRATING)->value = round($this->getStatByName(EPStat::$DURABILITY)->value * 1.5);
            }else{
               // error_log("DR 2");
                $this->getStatByName(EPStat::$DEATHRATING)->value = $this->getStatByName(EPStat::$DURABILITY)->value * 2;
            }
        }else{
            $this->getStatByName(EPStat::$DURABILITY)->value = 0;
            $this->getStatByName(EPStat::$DEATHRATING)->value = 0;
        }

        $this->getStatByName(EPStat::$WOUNDTHRESHOLD)->value = round($this->getStatByName(EPStat::$DURABILITY)->value / 5);
        $this->getStatByName(EPStat::$INITIATIVE)->value = ($this->getAptitudeByAbbreviation(EPAptitude::$INTUITION)
        + $this->getAptitudeByAbbreviation(EPAptitude::$REFLEXS))*2;
        $this->getStatByName(EPStat::$DAMAGEBONUS)->value = round($this->getAptitudeByAbbreviation(EPAptitude::$SOMATICS) / 10);
    }
    function  adjustAll(){
        $this->resetStartValues();

        $this->adjustWithBackgroundBonus();
        $this->adjustWithFactionBonus();
        $this->adjustWithMorphBonus();
        $this->adjustWithPsyBonus();
        $this->adjustWithSoftGearBonus();
        $this->adjustWithTraitBonus();

        $this->adjustValues();
        $this->adjustCredit();
        $this->adjustGrantedTraits();
    }
    function adjustGrantedTraits(){
         if (!empty($this->character->ego->background)){
             foreach ($this->character->ego->background->traits as $t) {
                 if ($t->isInArray($this->character->ego->additionalTraits)){
                     $this->removeTrait($t);
                 }
             }
        }
    }
    function resetStartValues(){
        foreach ($this->character->ego->aptitudes as $a){
            $a->maxValue = $this->configValues->getValue('RulesValues','AptitudesMaxValue');
            $a->minValue = $this->configValues->getValue('RulesValues','AptitudesMinValue');
            $a->maxEgoValue = $a->maxValue;
            $a->minEgoValue = $a->minValue;
            $a->maxMorphValue = $a->maxValue;
            $a->minMorphValue = 0;

            if ($a->activMorph){
                $a->maxMorphValue = $a->activMorph->maxApptitude;
            }
        }
        foreach ($this->character->ego->reputations as $r){
            if ($this->creationMode){
                $r->maxValue = $this->configValues->getValue('RulesValues','RepMaxPoint');
            }else{
                $r->maxValue = $this->configValues->getValue('RulesValues','EvoMaxRepValue');
            }
        }
        foreach ($this->character->ego->skills as $s){
            if ($this->creationMode){
                $s->maxValue = $this->configValues->getValue('RulesValues','SkillMaxPoint');
            }else{
                $s->maxValue = $this->configValues->getValue('RulesValues','SkillEvolutionMaxPoint');
            }
        }
        foreach ($this->character->ego->ais as $ia){
            foreach ($ia->aptitudes as $a){
                $a->maxValue = $this->configValues->getValue('RulesValues','AptitudesMaxValue');
                $a->minValue = $this->configValues->getValue('RulesValues','AptitudesMinValue');
                $a->maxEgoValue = $a->maxValue;
                $a->minEgoValue = $a->minValue;
                $a->maxMorphValue = $a->maxValue;
                $a->minMorphValue = $a->minValue;
            }
            foreach ($ia->skills as $s){
                if ($this->creationMode){
                    $s->maxValue = $this->configValues->getValue('RulesValues','SkillMaxPoint');
                }else{
                    $s->maxValue = $this->configValues->getValue('RulesValues','SkillEvolutionMaxPoint');
                }
            }
        }
    }
    function adjustValues(){
        foreach ($this->character->ego->aptitudes as $a){
            $newValue = $a->value;
            $max = $a->getMaxEgoValue();
            $min = $a->getMinEgoValue();
            if ($a->feebleMax){
                $newValue = min($newValue,4);
                $newValue = max($newValue,0);
            }else{
                $newValue = min($newValue,$max);
                $newValue = max($newValue,$min);
            }
            $this->setAptitudeValue($a->abbreviation, $newValue);
        }
        foreach ($this->character->ego->reputations as $a){
            $newValue = $a->value;
            $max = $a->getMaxValue();
            $absolute = $a->getAbsoluteValue();
            $newValue = min($newValue,$max);
            $newValue = min($newValue,$absolute);
            $this->setReputation($a->name, $newValue);
        }
        foreach ($this->character->ego->skills as $a){
            $maxValue = $a->getMaxValue() - $a->getBonusForCost();
            $newValue = min($maxValue,$a->baseValue);
            $this->setSkillValue($a->getUid(),$newValue);
        }
        foreach ($this->character->ego->ais as $ia){
                foreach ($ia->aptitudes as $a){
                $newValue = $a->value;
                $max = $a->getMaxEgoValue();
                $min = $a->getMinEgoValue();
                $newValue = min($newValue,$max);
                $newValue = max($newValue,$min);
                $this->setAiAptitudeValue($ia,$a->abbreviation, $newValue);
            }
            foreach ($ia->skills as $a){
              $maxValue = $a->getMaxValue() - $a->getBonusForCost();
              $newValue = min($maxValue,$a->baseValue);
              $this->setAiSkillValue($ia,$a->name,$newValue);
            }
        }
        $this->setStat(EPStat::$MOXIE, $newValue);
    }
    function adjustCredit(){
        $cred = $this->character->ego->credit + $this->character->ego->creditMorphMod + $this->character->ego->creditTraitMod + $this->character->ego->creditFactionMod + $this->character->ego->creditBackgroundMod + $this->character->ego->creditSoftGearMod + $this->character->ego->creditPsyMod;

        if (is_array($this->character->morphs)){
            foreach ($this->character->morphs as $m){
                if (!$m->buyInCreationMode){
                    $cred -= $m->getCost();
                }
                if (is_array($m->additionalGears)){
                    foreach ($m->additionalGears as $g){
                        $cred -= $g->getCost() * $g->occurence;
                    }
                }
            }
        }

        if (is_array($this->character->ego->ais)){
            foreach ($this->character->ego->ais as $ai){
                $cred -= $ai->getCost() * $ai->occurence;
            }
        }

        if (is_array($this->character->ego->softGears)){
            foreach ($this->character->ego->softGears as $s){
                $cred -= $s->getCost() * $s->occurence;
            }
        }

        $this->character->ego->creditInstant = $cred;
    }
    function adjustWithMorphBonus(){
        $this->character->ego->creditMorphMod = 0;
        $this->reputationPointsMorphMod = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $a->morphMod = 0;
            $a->ratioCostMorphMod = 1;
            $a->minEgoValueMorphMod = 0;
            $a->maxEgoValueMorphMod = 0;
            $a->minMorphValueMorphMod = 0;
            $a->maxMorphValueMorphMod = 0;
        }
        foreach ($this->character->ego->reputations as $r){
            $r->morphMod = 0;
            $r->ratioCostMorphMod = 1;
            $r->absoluteValueMorphMod = 1000;
            $r->maxValueMorphMod = 0;
        }
        foreach ($this->character->ego->skills as $s){
            $s->morphMod = 0;
            $s->ratioCostMorphMod = 1;
            $s->maxValueMorphMod = 0;
        }
        foreach ($this->character->morphs as $m) {
            $m->implantReject = false;
            foreach ($m->gears as $g){
                $g->armorPenetrationMorphMod = 0;
                $g->degatMorphMod = 0;
                $g->armorEnergyMorphMod = 0;
                $g->armorKineticMorphMod = 0;
                $g->ratioCostMorphMod = 1;
            }
            foreach ($m->additionalGears as $g){
                $g->armorPenetrationMorphMod = 0;
                $g->degatMorphMod = 0;
                $g->armorEnergyMorphMod = 0;
                $g->armorKineticMorphMod = 0;
                $g->ratioCostMorphMod = 1;
            }
        }
        if (is_array($this->character->ego->ais)){
            foreach ($this->character->ego->ais as $ia){
                foreach ($ia->aptitudes as $a){
                    $a->morphMod = 0;
                    $a->ratioCostMorphMod = 1;
                    $a->minEgoValueMorphMod = 0;
                    $a->maxEgoValueMorphMod = 0;
                    $a->minMorphValueMorphMod = 0;
                    $a->maxMorphValueMorphMod = 0;
                }
                foreach ($ia->skills as $s){
                    $s->morphMod = 0;
                    $s->ratioCostMorphMod = 1;
                    $s->maxValueMorphMod = 0;
                }
            }
        }
        foreach ($this->character->ego->stats as $s){
            $s->morphMod = 0;
            $s->ratioCostMorphMod = 1;
            $s->multiMorphMod = 1;
        }
        $currentMorph = $this->getCurrentMorph();
        if (isset($currentMorph)){
            if (is_array($currentMorph->gears) && count($currentMorph->gears) > 0){
                foreach ($currentMorph->gears as $g){
                    if (is_array($g->bonusMalus) && count($g->bonusMalus) > 0){
                        foreach ($g->bonusMalus as $bm){
                            $this->applyBonusMalus($bm,EPBonusMalus::$FROM_MORPH);
                        }
                    }
                }
            }
            if (is_array($currentMorph->additionalGears) && count($currentMorph->additionalGears) > 0){
                foreach ($currentMorph->additionalGears as $g){
                    if (is_array($g->bonusMalus) && count($g->bonusMalus) > 0){
                        foreach ($g->bonusMalus as $bm){
                            $this->applyBonusMalus($bm,EPBonusMalus::$FROM_MORPH);
                        }
                    }
                }
            }
            if (is_array($currentMorph->traits) && count($currentMorph->traits) > 0){
                foreach ($currentMorph->traits as $t){
                    if (is_array($t->bonusMalus) && count($t->bonusMalus) > 0){
                        foreach ($t->bonusMalus as $bm){
                            $this->applyBonusMalus($bm,EPBonusMalus::$FROM_MORPH);
                        }
                    }
                }
            }
            if (is_array($currentMorph->additionalTraits) && count($currentMorph->additionalTraits) > 0){
                foreach ($currentMorph->additionalTraits as $t){
                    if (is_array($t->bonusMalus) && count($t->bonusMalus) > 0){
                        foreach ($t->bonusMalus as $bm){
                            $this->applyBonusMalus($bm,EPBonusMalus::$FROM_MORPH);
                        }
                    }
                }
            }
            if (is_array($currentMorph->bonusMalus) && count($currentMorph->bonusMalus) > 0){
                foreach ($currentMorph->bonusMalus as $bm){
                    $this->applyBonusMalus($bm,EPBonusMalus::$FROM_MORPH);
                }
            }
        }
    }
    function adjustWithTraitBonus(){
        $this->character->ego->creditTraitMod = 0;
        $this->reputationPointsTraitMod = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $a->traitMod = 0;
            $a->ratioCostTraitMod = 1;
            $a->minEgoValueTraitMod = 0;
            $a->maxEgoValueTraitMod = 0;
            $a->minMorphValueTraitMod = 0;
            $a->maxMorphValueTraitMod = 0;
            $a->feebleMax = false;
        }
        foreach ($this->character->ego->reputations as $r){
            $r->traitMod = 0;
            $r->ratioCostTraitMod = 1;
            $r->absoluteValueTraitMod = 1000;
            $r->maxValueTraitMod = 0;
        }
        foreach ($this->character->ego->skills as $s){
            $s->traitMod = 0;
            $s->ratioCostTraitMod = 1;
            $s->maxValueTraitMod = 0;
        }
        foreach ($this->character->morphs as $m) {
            $m->implantReject = false;
            foreach ($m->gears as $g){
                $g->armorPenetrationTraitMod = 0;
                $g->degatTraitMod = 0;
                $g->armorEnergyTraitMod = 0;
                $g->armorKineticTraitMod = 0;
                $g->ratioCostTraitMod = 1;
            }
            foreach ($m->additionalGears as $g){
                $g->armorPenetrationTraitMod = 0;
                $g->degatTraitMod = 0;
                $g->armorEnergyTraitMod = 0;
                $g->armorKineticTraitMod = 0;
                $g->ratioCostTraitMod = 1;
            }
        }
        foreach ($this->character->ego->ais as $ia){
            foreach ($ia->aptitudes as $a){
                $a->traitMod = 0;
                $a->ratioCostTraitMod = 1;
                $a->minEgoValueTraitMod = 0;
                $a->maxEgoValueTraitMod = 0;
                $a->minMorphValueTraitMod = 0;
                $a->maxMorphValueTraitMod = 0;
                $a->feebleMax = false;
            }
            foreach ($ia->skills as $s){
                $s->traitMod = 0;
                $s->ratioCostTraitMod = 1;
                $s->maxValueTraitMod = 0;
            }
        }
        foreach ($this->character->ego->stats as $s){
            $s->traitMod = 0;
            $s->ratioCostTraitMod = 1;
            $s->multiTraitMod = 1;
        }
        foreach ($this->character->ego->traits as $t){
            foreach ($t->bonusMalus as $bm){
                $this->applyBonusMalus($bm,EPBonusMalus::$FROM_TRAIT);
            }
        }
        foreach ($this->character->ego->additionalTraits as $t){
            foreach ($t->bonusMalus as $bm){
                $this->applyBonusMalus($bm,EPBonusMalus::$FROM_TRAIT);
            }
        }
    }
    function adjustWithFactionBonus(){
        $this->character->ego->creditFactionMod = 0;
        $this->reputationPointsFactionMod = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $a->factionMod = 0;
            $a->ratioCostFactionMod = 1;
            $a->minEgoValueFactionMod = 0;
            $a->maxEgoValueFactionMod = 0;
            $a->minMorphValueFactionMod = 0;
            $a->maxMorphValueFactionMod = 0;
        }
        foreach ($this->character->ego->reputations as $r){
            $r->factionMod = 0;
            $r->ratioCostFactionMod = 1;
            $r->absoluteValueFactionMod = 1000;
            $r->maxValueFactionMod = 0;
        }
        foreach ($this->character->ego->skills as $s){
            $s->factionMod = 0;
            $s->ratioCostFactionMod = 1;
            $s->maxValueFactionMod = 0;
        }
        foreach ($this->character->morphs as $m) {
            foreach ($m->gears as $g){
                $g->armorPenetrationFactionMod = 0;
                $g->degatFactionMod = 0;
                $g->armorEnergyFactionMod = 0;
                $g->armorKineticFactionMod = 0;
                $g->ratioCostFactionMod = 1;
            }
            foreach ($m->additionalGears as $g){
                $g->armorPenetrationFactionMod = 0;
                $g->degatFactionMod = 0;
                $g->armorEnergyFactionMod = 0;
                $g->armorKineticFactionMod = 0;
                $g->ratioCostFactionMod = 1;
            }
        }
        foreach ($this->character->ego->ais as $ia){
            foreach ($ia->aptitudes as $a){
                $a->factionMod = 0;
                $a->ratioCostFactionMod = 1;
                $a->minEgoValueFactionMod = 0;
                $a->maxEgoValueFactionMod = 0;
                $a->minMorphValueFactionMod = 0;
                $a->maxMorphValueFactionMod = 0;
            }
            foreach ($ia->skills as $s){
                $s->factionMod = 0;
                $s->ratioCostFactionMod = 1;
                $s->maxValueFactionMod = 0;
            }
        }
        foreach ($this->character->ego->stats as $s){
            $s->factionMod = 0;
            $s->ratioCostFactionMod = 1;
            $s->multiFactionMod = 1;
        }
        if (isset($this->character->ego->faction)){
            foreach ($this->character->ego->faction->bonusMalus as $bm){
                $this->applyBonusMalus($bm,EPBonusMalus::$FROM_FACTION);
            }
        }
    }
    function adjustWithBackgroundBonus(){
        $this->character->ego->creditBackgroundMod = 0;
        $this->reputationPointsBackgroundMod = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $a->backgroundMod = 0;
            $a->ratioCostBackgroundMod = 1;
            $a->minEgoValueBackgroundMod = 0;
            $a->maxEgoValueBackgroundMod = 0;
            $a->minMorphValueBackgroundMod = 0;
            $a->maxMorphValueBackgroundMod = 0;
        }
        foreach ($this->character->ego->reputations as $r){
            $r->backgroundMod = 0;
            $r->ratioCostBackgroundMod = 1;
            $r->absoluteValueBackgroundMod = 1000;
            $r->maxValueBackgroundMod = 0;
        }
        foreach ($this->character->ego->skills as $s){
            $s->backgroundMod = 0;
            $s->ratioCostBackgroundMod = 1;
            $s->maxValueBackgroundMod = 0;
        }
        foreach ($this->character->morphs as $m) {
            foreach ($m->gears as $g){
                $g->armorPenetrationBackgroundMod = 0;
                $g->degatBackgroundMod = 0;
                $g->armorEnergyBackgroundMod = 0;
                $g->armorKineticBackgroundMod = 0;
                $g->ratioCostBackgroundMod = 1;
            }
            foreach ($m->additionalGears as $g){
                $g->armorPenetrationBackgroundMod = 0;
                $g->degatBackgroundMod = 0;
                $g->armorEnergyBackgroundMod = 0;
                $g->armorKineticBackgroundMod = 0;
                $g->ratioCostBackgroundMod = 1;
            }
        }
        foreach ($this->character->ego->ais as $ia){
            foreach ($ia->aptitudes as $a){
                $a->backgroundMod = 0;
                $a->ratioCostBackgroundMod = 1;
                $a->minEgoValueBackgroundMod = 0;
                $a->maxEgoValueBackgroundMod = 0;
                $a->minMorphValueBackgroundMod = 0;
                $a->maxMorphValueBackgroundMod = 0;
            }
            foreach ($ia->skills as $s){
                $s->backgroundMod = 0;
                $s->ratioCostBackgroundMod = 1;
                $s->maxValueBackgroundMod = 0;
            }
        }
        foreach ($this->character->ego->stats as $s){
            $s->backgroundMod = 0;
            $s->ratioCostBackgroundMod = 1;
            $s->multiBackgroundMod = 1;
        }
        if (isset($this->character->ego->background)){
            foreach ($this->character->ego->background->bonusMalus as $bm){
                $this->applyBonusMalus($bm,EPBonusMalus::$FROM_BACKGROUND);
            }
        }
    }
    function adjustWithSoftGearBonus(){
        $this->character->ego->creditSoftGearMod = 0;
        $this->reputationPointsSoftGearMod = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $a->softgearMod = 0;
            $a->ratioCostSoftgearMod = 1;
            $a->minEgoValueSoftgearMod = 0;
            $a->maxEgoValueSoftgearMod = 0;
            $a->minMorphValueSoftgearMod = 0;
            $a->maxMorphValueSoftgearMod = 0;
        }
        foreach ($this->character->ego->reputations as $r){
            $r->softgearMod = 0;
            $r->ratioCostSoftgearMod = 1;
            $r->absoluteValueSoftgearMod = 1000;
            $r->maxValueSoftgearMod = 0;
        }
        foreach ($this->character->ego->skills as $s){
            $s->softgearMod = 0;
            $s->ratioCostSoftgearMod = 1;
            $s->maxValueSofgearMod = 0;
        }
        foreach ($this->character->morphs as $m) {
            foreach ($m->gears as $g){
                $g->armorPenetrationSoftgearMod = 0;
                $g->degatSoftgearMod = 0;
                $g->armorEnergySoftgearMod = 0;
                $g->armorKineticSoftgearMod = 0;
                $g->ratioCostSoftgearMod = 1;
            }
            foreach ($m->additionalGears as $g){
                $g->armorPenetrationSoftgearMod = 0;
                $g->degatSoftgearMod = 0;
                $g->armorEnergySoftgearMod = 0;
                $g->armorKineticSoftgearMod = 0;
                $g->ratioCostSoftgearMod = 1;
            }
        }
        foreach ($this->character->ego->ais as $ia){
            foreach ($ia->aptitudes as $a){
                $a->softgearMod = 0;
                $a->ratioCostSoftgearMod = 1;
                $a->minEgoValueSoftgearMod = 0;
                $a->maxEgoValueSoftgearMod = 0;
                $a->minMorphValueSoftgearMod = 0;
                $a->maxMorphValueSoftgearMod = 0;
            }
            foreach ($ia->skills as $s){
                $s->softgearMod = 0;
                $s->ratioCostSoftgearMod = 1;
                $s->maxValueSofgearMod = 0;
            }
        }
        foreach ($this->character->ego->stats as $s){
            $s->softgearMod = 0;
            $s->ratioCostSoftgearMod = 1;
            $s->multiSoftgearMod = 1;
        }
        foreach ($this->character->ego->softGears as $sg){
            foreach ($sg->bonusMalus as $bm){
                $this->applyBonusMalus($bm,EPBonusMalus::$FROM_SOFTGEAR);
            }
        }
    }
    function adjustWithPsyBonus(){
        $this->character->ego->creditPsyMod = 0;
        $this->reputationPointsPsyMod = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $a->psyMod = 0;
            $a->ratioCostPsyMod = 1;
            $a->minEgoValuePsyMod = 0;
            $a->maxEgoValuePsyMod = 0;
            $a->minMorphValuePsyMod = 0;
            $a->maxMorphValuePsyMod = 0;
        }
        foreach ($this->character->ego->reputations as $r){
            $r->psyMod = 0;
            $r->ratioCostPsyMod = 1;
            $r->absoluteValuePsyMod = 1000;
            $r->maxValuePsyMod = 0;
        }
        foreach ($this->character->ego->skills as $s){
            $s->psyMod = 0;
            $s->ratioCostPsyMod = 1;
            $s->maxValuePsyMod = 0;
        }
        foreach ($this->character->morphs as $m) {
            foreach ($m->gears as $g){
                $g->armorPenetrationPsyMod = 0;
                $g->degatPsyMod = 0;
                $g->armorEnergyPsyMod = 0;
                $g->armorKineticPsyMod = 0;
                $g->ratioCostPsyMod = 1;
            }
            foreach ($m->additionalGears as $g){
                $g->armorPenetrationPsyMod = 0;
                $g->degatPsyMod = 0;
                $g->armorEnergyPsyMod = 0;
                $g->armorKineticPsyMod = 0;
                $g->ratioCostPsyMod = 1;
            }
        }
        foreach ($this->character->ego->ais as $ia){
            foreach ($ia->aptitudes as $a){
                $a->psyMod = 0;
                $a->ratioCostPsyMod = 1;
                $a->minEgoValuePsyMod = 0;
                $a->maxEgoValuePsyMod = 0;
                $a->minMorphValuePsyMod = 0;
                $a->maxMorphValuePsyMod = 0;
            }
            foreach ($ia->skills as $s){
                $s->psyMod = 0;
                $s->ratioCostPsyMod = 1;
                $s->maxValuePsyMod = 0;
            }
        }
        foreach ($this->character->ego->stats as $s){
            $s->psyMod = 0;
            $s->ratioCostPsyMod = 1;
            $s->multiPsyMod = 1;
        }
        foreach ($this->character->ego->psySleights as $ps){
            if ($ps->isActif === true || $ps->action === EPPsySleight::$ACTION_AUTOMATIC)
            foreach ($ps->bonusMalus as $bm){
                $this->applyBonusMalus($bm,EPBonusMalus::$FROM_PSY);
            }
        }
    }
    function getCostForApts(){
        $cost = $this->getSumAptitudes();
        $cost = max(0,$cost - $this->configValues->getValue('RulesValues','AptitudesPoint'));
        return $cost * $this->configValues->getValue('RulesValues','AptitudePointCost');
    }
    function getCostForStats(){
        $cost = $this->getStatByAbbreviation(EPStat::$MOXIE)->value - $this->configValues->getValue('RulesValues','MoxieStartValue');
        return $cost * $this->configValues->getValue('RulesValues','MoxiePointCost');
    }
    function getCostForMorphs(){
        $cost = 0;
        foreach ($this->character->morphs as $m){
            if($m->buyInCreationMode){
                $cost += $m->cpCost;
            }
        }
        return $cost;
    }
    // If all Reputation points are spent, then CP/RP is used to make up the difference
    function getCostForReputation(){
        $c = $this->getReputationPointsRemaining();
        if ($c < 0){
            return abs($c) * $this->configValues->getValue('RulesValues','RepPointCost');
        }
        return 0;
    }
    function getCostForSkills(){
        $cost = 0;
        foreach ($this->character->ego->skills as $s){
                 $cost += $this->getRealCPCostForSkill($s);
                 if (!empty($s->specialization)){
                     $cost += $this->configValues->getValue('RulesValues','SpecializationCost');;
                 }
        }
        return $cost;
    }
    function getCostForPsysleights(){
        $cost = 0;
        foreach ($this->character->ego->psySleights as $p){
                 $cost +=  $this->configValues->getValue('RulesValues','PsyCpCost');
        }
        return $cost;
    }
    function getRealCPCostForSkill(EPSkill $skill){
        if ($skill->isNativeTongue === true){
            $skill->isNativeTongue = false;

            $val = $skill->baseValue;

            $skill->baseValue += $this->configValues->getValue('RulesValues','NativeTongueBonus');
            $cost1 =  $this->getRealCPCostForSkill($skill);

            $skill->baseValue = $this->configValues->getValue('RulesValues','NativeTongueBonus');
            $cost2 =  $this->getRealCPCostForSkill($skill);

            $res = $cost1 - $cost2;

            $skill->isNativeTongue = true;
            $skill->baseValue = $val;
            return $res;
        }else{
            $downPart = max(0,$this->configValues->getValue('RulesValues','SkillLimitForImprove') -$skill->getBonusForCost());
            $downPart = min($downPart,$skill->baseValue);
            $upPart = $skill->baseValue + $skill->getBonusForCost() - $this->configValues->getValue('RulesValues','SkillLimitForImprove');
            $upPart = max(0,$upPart);
            $upPart = min($upPart,$skill->baseValue);

            return $downPart * $this->configValues->getValue('RulesValues','SkillPointUnderCost') * $skill->getRatioCost()
                + $upPart * $this->configValues->getValue('RulesValues','SkillPointUpperCost') * $skill->getRatioCost();
        }
    }
    function getReputationByName($name){
        $ret = EPAtom::getAtomByName($this->character->ego->reputations,$name);
        if($ret == null){
            array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (This Reputation not exist !)', EPCreatorErrors::$SYSTEM_ERROR));
        }
        return $ret;
    }
    function getReputations(){
        return $this->character->ego->reputations;
    }

    private function getDiffCost($sk,$value){
        $val = $sk->baseValue;
        $vStart = $this->getRealCPCostForSkill($sk);
        $sk->baseValue = $value;
        $vEnd = $this->getRealCPCostForSkill($sk);
        $sk->baseValue = $val;
        return $vEnd - $vStart;
    }
    private function loadAptitudes(){
        $this->character->ego->aptitudes = $this->listProvider->getListAptitudes($this->configValues->getValue('RulesValues','AptitudesMinValue'),
                                                      $this->configValues->getValue('RulesValues','AptitudesMaxValue'));
        $this->aptitudePoints -= count($this->character->ego->aptitudes) * $this->configValues->getValue('RulesValues','AptitudesMinValue');
    }
    private function loadGroups(){
        $this->groups = $this->listProvider->getListGroups();
    }
    private function loadPrefixs(){
        $this->prefixs = $this->listProvider->getListPrefix();
    }
    private function loadReps(){
        $this->character->ego->reputations = $this->listProvider->getListReputation();
    }
    private function loadSkills(){
        $this->character->ego->skills = $this->listProvider->getListSkills($this->character->ego->aptitudes);
    }
    private function loadStats(){
        $this->character->ego->stats = $this->listProvider->getListStats($this->configValues,$this);
    }
    private function loadBackgrounds(){
        $this->backgrounds = $this->listProvider->getListBackgrounds();
    }
    private function loadMorphs(){
        $this->morphs = $this->listProvider->getListMorph();
    }
    private function loadAis(){
        $this->ais = $this->listProvider->getListAi();
    }
    private function loadGears(){
        $this->gears = $this->listProvider->getListGears();
    }
    private function loadTraits(){
        $this->traits = $this->listProvider->getListTraits();
    }
    private function loadPsySleights(){
	    $this->psySleights = $this->listProvider->getListPsySleights();
    }

    private function prefixExist($prefix){
        foreach ($this->prefixs as $p){
            if ($p == $prefix){
                return true;
            }
        }
        return false;
    }
    function purchaseCredit($cpAmount){
        if ($this->creationMode){
            if ($cpAmount + $this->character->ego->creditPurchased * $this->configValues->getValue('RulesValues','CreditPointCost') > $this->configValues->getValue('RulesValues','MaxCreditPurchaseWithCp')){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (Max 100 CP!)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }

            $cred = $cpAmount / $this->configValues->getValue('RulesValues','CreditPointCost');
            $this->character->ego->creditPurchased += $cred;
            $this->character->ego->credit += $cred;

            return true;
        }else{
            $cred = $cpAmount / $this->configValues->getValue('RulesValues','CreditPointCost');
            $this->evoCrePointPurchased += $cred;
            return true;
        }
    }
    function saleCredit($cpAmount){
        if ($this->creationMode){
            $cred = $cpAmount / $this->configValues->getValue('RulesValues','CreditPointCost');
            if ($cred > $this->character->ego->creditPurchased){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No enough crédits purchased !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            if ($cred > $this->getCredit()){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (No enough crédits !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }
            $this->character->ego->creditPurchased -= $cred;
            $this->character->ego->credit -= $cred;

            return true;
        }else{
            $cred = $cpAmount / $this->configValues->getValue('RulesValues','CreditPointCost');
            if ($cred > $this->evoCrePointPurchased){
                array_push($this->errorList, new EPCreatorErrors('EPCharacterCreator:'.__LINE__.' (To credits sales (max:'.$this->evoCrePointPurchased.') !)', EPCreatorErrors::$RULE_ERROR));
                return false;
            }else{
                $this->evoCrePointPurchased -= $cred;
                return true;
            }
        }
    }

    function getSumPosTraits(){
        $tot = 0;
        foreach ($this->character->ego->additionalTraits as $t) {
            if ($t->isPositive()){
                $tot += $t->cpCost;
            }
        }
        foreach ($this->character->morphs as $m) {
            foreach ($m->additionalTraits as $t) {
                if ($t->isPositive()){
                    $tot += $t->cpCost;
                }
            }
        }

        return $tot;
    }
    function getSumNegTraits(){
        $tot = 0;
        foreach ($this->character->ego->additionalTraits as $t) {
            if ($t->isNegative()){
                $tot += $t->cpCost;
            }
        }
        $tot += $this->getSumNegMorphTraits();
        return $tot;
    }
    function getSumNegMorphTraits(){
        $tot = 0;
        foreach ($this->character->morphs as $morph) {
            foreach ($morph->additionalTraits as $t) {
                if ($t->isNegative()){
                    $tot += $t->cpCost;
                }
            }
        }
        return $tot;
    }

    private function getSumAptitudes(){
        $res = 0;
        foreach ($this->character->ego->aptitudes as $a){
            $res += $a->value;
        }
        return $res;
    }
    private function getSumRepPoints(){
        $res = 0;
        foreach ($this->character->ego->reputations as $r){
            $res += $r->getValue();
        }
        return $res;
    }
    function activePsySleights(){
        foreach ($this->character->ego->psySleights as $p){
            $p->isActif = true;
        }
    }
    function unactivePsySleights(){
        foreach ($this->character->ego->psySleights as $p){
            $p->isActif = false;
        }
    }
    function getCurrentPsySleights(){
	    return $this->character->ego->psySleights;
    }
    function getPsySleights(){
	    return $this->psySleights;
    }
    function getPsySleightsByName($name){
        return EPAtom::getAtomByName($this->psySleights,$name);
    }

    /**
     * Thousand plus line function for applying bonusMalus to everything!
     *
     * @param $bm EPBonusMalus        - The bonusMalus in question.
     * @param $source string    - Where the bonusMalus is coming from.
     *                      Acceptable values are:
     *                          EPBonusMalus::$FROM_MORPH
     *                          EPBonusMalus::$FROM_TRAIT
     *                          EPBonusMalus::$FROM_FACTION
     *                          EPBonusMalus::$FROM_BACKGROUND
     *                          EPBonusMalus::$FROM_SOFTGEAR
     *                          EPBonusMalus::$FROM_PSY
     */
    private function applyBonusMalus($bm,$source){
        switch ($bm->bonusMalusType) {
            case EPBonusMalus::$ON_SPECIAL_01: // Special for Feeble negative trait
                foreach ($this->character->ego->aptitudes as $a){
                    if (strcmp($bm->forTargetNamed,$a->name) == 0){
                        $a->feebleMax = true;
                        $a->maxValue = min(4,$a->maxValue);
                        $a->minValue = min(0,$a->minValue);
                    }
                }                 
            break;
            case EPBonusMalus::$ON_SPECIAL_02: // Special for implant reject level II trait
                foreach ($this->character->morphs as $m) {
                    if ($this->morphHaveBonusMalus($bm, $m)){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $m->implantReject = true;  
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,  EPGear::$IMPLANT_GEAR) == 0){
                                        $this->removeGear($g, $m);
                                    }
                                }
                            break;                   
                        }                        
                    }                    
                }                
            break;
            case EPBonusMalus::$ON_APTITUDE:
                foreach ($this->character->ego->aptitudes as $a){
                    if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $a->morphMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_TRAIT:
                                $a->traitMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_BACKGROUND:
                                $a->backgroundMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_FACTION:
                                $a->factionMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                $a->softgearMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_PSY:
                                $a->psyMod += $bm->value;
                            break;
                        }
                    }
                }
            break;
            case EPBonusMalus::$ON_APTITUDE_EGO_MAX:
                foreach ($this->character->ego->aptitudes as $a){
                    if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $a->maxEgoValueMorphMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_TRAIT:
                                $a->maxEgoValueTraitMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_BACKGROUND:
                                $a->maxEgoValueBackgroundMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_FACTION:
                                $a->maxEgoValueFactionMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                 $a->maxEgoValueSoftgearMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_PSY:
                                $a->maxEgoValuePsyMod += $bm->value;
                            break;
                        }
                    }
                }
            break;
            case EPBonusMalus::$ON_APTITUDE_MORPH_MAX:
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:                        
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->maxMorphValueMorphMod += $bm->value;
                            }
                        }                                               
                    break;               
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->maxMorphValueTraitMod += $bm->value;
                            }
                        } 
                    break;               
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->maxMorphValueBackgroundMod += $bm->value;
                            }
                        }
                    break;               
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->maxMorphValueFactionMod += $bm->value;
                            }
                        }
                    break;
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->maxMorphValueSoftgearMod += $bm->value;
                            }
                        }
                    break;  
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->maxMorphValuePsyMod += $bm->value;
                            }
                        }
                    break;  
                }
            break;
            case EPBonusMalus::$ON_APTITUDE_MORPH_MIN:
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:                        
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minMorphValueMorphMod += $bm->value;
                            }
                        }                                               
                    break;               
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minMorphValueTraitMod += $bm->value;
                            }
                        } 
                    break;               
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minMorphValueBackgroundMod += $bm->value;
                            }
                        }
                    break;               
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minMorphValueFactionMod += $bm->value;
                            }
                        }
                    break;
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minMorphValueSoftgearMod += $bm->value;
                            }
                        }
                    break;  
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minMorphValuePsyMod += $bm->value;
                            }
                        }
                    break;  
                }
            break;
            case EPBonusMalus::$ON_APTITUDE_EGO_MIN:
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:                        
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minEgoValueMorphMod += $bm->value;
                            }
                        }                                               
                    break;               
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minEgoValueTraitMod += $bm->value;
                            }
                        } 
                    break;               
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minEgoValueBackgroundMod += $bm->value;
                            }
                        }
                    break;               
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minEgoValueFactionMod += $bm->value;
                            }
                        }
                    break;
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minEgoValueSoftgearMod += $bm->value;
                            }
                        }
                    break;  
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($this->character->ego->aptitudes as $a){
                            if (strcmp($bm->forTargetNamed,$a->name) == 0 || EPAtom::isInGroups($a,$bm->groups)){
                                $a->minEgoValuePsyMod += $bm->value;
                            }
                        }
                    break;  
                }
            break;
            case EPBonusMalus::$ON_SKILL:
                $group_members = EPSkill::getGroupMembers($this->character->ego->skills,$bm->groups);
                $skill = EPAtom::getAtomByUid($this->character->ego->skills,$bm->forTargetNamed);
                // Database skills (non user selectable) use name/prefix instead of Uid
                if($skill == null){
                    $skill = EPSkill::getSkill($this->character->ego->skills,$bm->forTargetNamed,$bm->typeTarget);
                }
                // Just in case
                if($skill != null){
                    array_push($group_members,$skill);
                }
                foreach ($group_members as $s){
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            if ($bm->onCost == 'true'){
                                $s->ratioCostMorphMod *= $bm->value;
                            }else{
                                $s->morphMod += $bm->value;
                            }
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            if ($bm->onCost == 'true'){
                                $s->ratioCostTraitMod *= $bm->value;
                            }else{
                                $s->traitMod += $bm->value;
                            }
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            if ($bm->onCost == 'true'){
                                $s->ratioCostFactionMod *= $bm->value;
                            }else{
                                $s->factionMod += $bm->value;
                            }
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            if ($bm->onCost == 'true'){
                                $s->ratioCostBackgroundMod *= $bm->value;
                            }else{
                                $s->backgroundMod += $bm->value;
                            }
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            if ($bm->onCost == 'true'){
                                $s->ratioCostSoftgearMod *= $bm->value;
                            }else{
                                $s->softgearMod += $bm->value;
                            }
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            if ($bm->onCost == 'true'){
                                $s->ratioCostPsyMod *= $bm->value;
                            }else{
                                $s->psyMod += $bm->value;
                            }
                        break;
                    }
                }
            break;
            case EPBonusMalus::$ON_SKILL_MAX:
                $group_members = EPAtom::getGroupMembers($this->character->ego->skills,$bm->groups);
                $skill = EPAtom::getAtomByUid($this->character->ego->skills,$bm->forTargetNamed);
                // Database skills (non user selectable) use name/prefix instead of Uid
                if($skill == null){
                    $skill = EPSkill::getSkill($this->character->ego->skills,$bm->forTargetNamed,$bm->typeTarget);
                }
                // Just in case
                if($skill != null){
                    array_push($group_members,$skill);
                }
                foreach ($group_members as $s){
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            $s->maxValueMorphMod += $bm->value;
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            $s->maxValueTraitMod += $bm->value;
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            $s->maxValueFactionMod += $bm->value;
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            $s->maxValueBackgroundMod += $bm->value;
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            $s->maxValueSoftgearMod += $bm->value;
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            $s->maxValuePsyMod += $bm->value;
                        break;
                    }
                }
            break;
            case EPBonusMalus::$ON_SKILL_PREFIX:
                $ls = $this->getSkillsByPrefix($bm->forTargetNamed);
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:
                        foreach ($ls as $s){
                            $s->morphMod += $bm->value;
                        } 
                    break;
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($ls as $s){
                            $s->traitMod += $bm->value;
                        } 
                    break;
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($ls as $s){
                            $s->factionMod += $bm->value;
                        } 
                    break;   
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($ls as $s){
                            $s->backgroundMod += $bm->value;
                        } 
                    break;                    
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($ls as $s){
                            $s->softgearMod += $bm->value;
                        } 
                    break; 
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($ls as $s){
                            $s->psyMod += $bm->value;
                        } 
                    break;                     
                }
            break;
            case EPBonusMalus::$ON_SKILL_TYPE:
                foreach ($this->character->ego->skills as $s){
                    if (strcmp($s->skillType,$bm->forTargetNamed) == 0){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $s->morphMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_TRAIT:
                                $s->traitMod += $bm->value;
                            break;  
                            case EPBonusMalus::$FROM_FACTION:
                                $s->factionMod += $bm->value;
                            break;    
                            case EPBonusMalus::$FROM_BACKGROUND:
                                $s->backgroundMod += $bm->value;
                            break;                        
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                $s->softgearMod += $bm->value;
                            break; 
                            case EPBonusMalus::$FROM_PSY:
                                $s->psyMod += $bm->value;
                            break;                        
                        } 
                    }                        
                }
            break;
            case EPBonusMalus::$ON_ARMOR:
                $m = $this->getCurrentMorph(); 
                if (isset($m)){ 
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostMorphMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyMorphMod += $bm->value;
                                        $g->armorKineticMorphMod += $bm->value;                                        
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostTraitMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyTraitMod += $bm->value;
                                        $g->armorKineticTraitMod += $bm->value;                                        
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostBackgroundMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyBackgroundMod += $bm->value;
                                        $g->armorKineticBackgroundMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostFactionMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyFactionMod += $bm->value;
                                        $g->armorKineticFactionMod += $bm->value;                                        
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostSoftgearMod *= $bm->value;
                                    }else{
                                        $g->armorEnergySoftgearMod += $bm->value;
                                        $g->armorKineticSoftgearMod += $bm->value;                                        
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostPsyMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyPsyMod += $bm->value;
                                        $g->armorKineticPsyMod += $bm->value;                                        
                                    }
                                } 
                            }
                        break;                        
                    }
                }                  
            break;
            case EPBonusMalus::$ON_ENERGY_ARMOR:
                $m = $this->getCurrentMorph(); 
                if (isset($m)){  
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostMorphMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyMorphMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostTraitMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyTraitMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostBackgroundMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyBackgroundMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostFactionMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyFactionMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioSoftgearPsyMod *= $bm->value;
                                    }else{
                                        $g->armorEnergySoftgearMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostPsyMod *= $bm->value;
                                    }else{
                                        $g->armorEnergyPsyMod += $bm->value;
                                    }
                                } 
                            }
                        break;                        
                    }
                }                  
            break;
            case EPBonusMalus::$ON_KINETIC_ARMOR:
                $m = $this->getCurrentMorph(); 
                if (isset($m)){  
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostMorphMod *= $bm->value;
                                    }else{
                                        $g->armorKineticMorphMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostTraitMod *= $bm->value;
                                    }else{
                                        $g->armorKineticTraitMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostBackgroundMod *= $bm->value;
                                    }else{
                                        $g->armorKineticBackgroundMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostFactionMod *= $bm->value;
                                    }else{
                                        $g->armorKineticFactionMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostSoftgearMod *= $bm->value;
                                    }else{
                                        $g->armorKineticSoftgearMod += $bm->value;
                                    }
                                } 
                            }
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$ARMOR_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostPsyMod *= $bm->value;
                                    }else{
                                        $g->armorKineticPsyMod += $bm->value;
                                    }
                                } 
                            }
                        break;                        
                    }
                }         
            break;
            case EPBonusMalus::$ON_ENERGY_WEAPON_DAMAGE:
                $m = $this->getCurrentMorph();
                if (isset($m)){
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_ENERGY_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostMorphMod *= $bm->value;
                                    }else{
                                        $g->degatMorphMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_ENERGY_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostTraitMod *= $bm->value;
                                    }else{
                                        $g->degatTraitMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_ENERGY_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostBackgroundMod *= $bm->value;
                                    }else{
                                        $g->degatBackgroundMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_ENERGY_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostFactionMod *= $bm->value;
                                    }else{
                                        $g->degatFactionMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_ENERGY_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostSoftgearMod *= $bm->value;
                                    }else{
                                        $g->degatSoftgearMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_ENERGY_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostPsyMod *= $bm->value;
                                    }else{
                                        $g->degatPsyMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;                        
                    }
                }
            break;
            case EPBonusMalus::$ON_MELEE_WEAPON_DAMAGE:
                $m = $this->getCurrentMorph();
                if (isset($m)){
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_MELEE_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostMorphMod *= $bm->value;
                                    }else{
                                        $g->degatMorphMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_MELEE_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostTraitMod *= $bm->value;
                                    }else{
                                        $g->degatTraitMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_MELEE_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostBackgroundMod *= $bm->value;
                                    }else{
                                        $g->degatBackgroundMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_MELEE_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostFactionMod *= $bm->value;
                                    }else{
                                        $g->degatFactionMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_MELEE_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostSoftgearMod *= $bm->value;
                                    }else{
                                        $g->degatSoftgearMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_MELEE_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostPsyMod *= $bm->value;
                                    }else{
                                        $g->degatPsyMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;                        
                    }
                }
            break;
            case EPBonusMalus::$ON_KINETIC_WEAPON_DAMAGE:
                $m = $this->getCurrentMorph();
                if (isset($m)){
                    switch ($source) {
                        case EPBonusMalus::$FROM_MORPH:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_KINETIC_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostMorphMod *= $bm->value;
                                    }else{
                                        $g->degatMorphMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_TRAIT:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_KINETIC_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostTraitMod *= $bm->value;
                                    }else{
                                        $g->degatTraitMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_BACKGROUND:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_KINETIC_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostBackgroundMod *= $bm->value;
                                    }else{
                                        $g->degatBackgroundMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_FACTION:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_KINETIC_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostFactionMod *= $bm->value;
                                    }else{
                                        $g->degatFactionMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_SOFTGEAR:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_KINETIC_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostSoftgearMod *= $bm->value;
                                    }else{
                                        $g->degatSoftgearMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;
                        case EPBonusMalus::$FROM_PSY:
                            foreach ($m->gears as $g){
                                if (strcmp($g->gearType,EPGear::$WEAPON_KINETIC_GEAR) == 0){
                                    if ($bm->onCost == 'true'){
                                        $g->ratioCostPsyMod *= $bm->value;
                                    }else{
                                        $g->degatPsyMod += $bm->value;
                                    }
                                } 
                            }                            
                        break;                        
                    }
                }
            break;
            case EPBonusMalus::$ON_REPUTATION:
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->morphMod += $bm->value;
                            }
                        }                        
                    break;
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->traitMod += $bm->value;
                            }
                        }                        
                    break;
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->factionMod += $bm->value;
                            }
                        }                        
                    break; 
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->backgroundMod += $bm->value;
                            }
                        }                        
                    break;                
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->softgearMod += $bm->value;
                            }
                        }                        
                    break;     
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->psyMod += $bm->value;
                            }
                        }                        
                    break;                     
                }                 
            break; 
            case EPBonusMalus::$ON_GROUP:
                // On passe en revue les skills et si le skill appartient au group on lui applique le bm
                foreach ($this->character->ego->skills as $s){
                    if (EPAtom::isInGroups($s,$bm->forTargetNamed)){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $s->morphMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_TRAIT:
                                $s->traitMod += $bm->value;
                            break;  
                            case EPBonusMalus::$FROM_FACTION:
                                $s->factionMod += $bm->value;
                            break;    
                            case EPBonusMalus::$FROM_BACKGROUND:
                                $s->backgroundMod += $bm->value;
                            break;                        
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                $s->softgearMod += $bm->value;
                            break; 
                            case EPBonusMalus::$FROM_PSY:
                                $s->psyMod += $bm->value;
                            break;                        
                        } 
                    }                        
                }
            break;
            case EPBonusMalus::$ON_STAT:
                foreach ($this->character->ego->stats as $st) {
                    if (strcmp($st->name,$bm->forTargetNamed) === 0){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $st->morphMod += $bm->value;
                            break;
                            case EPBonusMalus::$FROM_TRAIT:
                                $st->traitMod += $bm->value;
                            break;  
                            case EPBonusMalus::$FROM_FACTION:
                                $st->factionMod += $bm->value;
                            break;    
                            case EPBonusMalus::$FROM_BACKGROUND:
                                $st->backgroundMod += $bm->value;
                            break;                        
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                $st->softgearMod += $bm->value;
                            break; 
                            case EPBonusMalus::$FROM_PSY:
                                $st->psyMod += $bm->value;
                            break;                        
                        } 
                    }                    
                }
            break;
            case EPBonusMalus::$ON_STAT_MULTIPLI:
                foreach ($this->character->ego->stats as $st) {
                    if (strcmp($st->name,$bm->forTargetNamed) === 0){
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                $st->multiMorphMod *= $bm->value;
                            break;
                            case EPBonusMalus::$FROM_TRAIT:
                                $st->multiTraitMod *= $bm->value;
                            break;  
                            case EPBonusMalus::$FROM_FACTION:
                                $st->multiFactionMod *= $bm->value;
                            break;    
                            case EPBonusMalus::$FROM_BACKGROUND:
                                $st->multiBackgroundMod *= $bm->value;
                            break;                        
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                $st->multiSoftgearMod *= $bm->value;
                            break; 
                            case EPBonusMalus::$FROM_PSY:
                                $st->multiPsyMod *= $bm->value;
                            break;                        
                        } 
                    }                    
                }
            break;
            case EPBonusMalus::$ON_CREDIT:  
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:
                        $this->character->ego->creditMorphMod += $bm->value;
                    break;
                    case EPBonusMalus::$FROM_TRAIT:
                        $this->character->ego->creditTraitMod += $bm->value;
                    break;  
                    case EPBonusMalus::$FROM_FACTION:
                        $this->character->ego->creditFactionMod += $bm->value;
                    break;    
                    case EPBonusMalus::$FROM_BACKGROUND:
                        $this->character->ego->creditBackgroundMod += $bm->value;
                    break;                        
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        $this->character->ego->creditSoftGearMod += $bm->value;
                    break; 
                    case EPBonusMalus::$FROM_PSY:
                        $this->character->ego->creditPsyMod += $bm->value;
                    break;                        
                }
            break;
            case EPBonusMalus::$ON_MORPH:
                // Sur le oncost (facteur qui change)
                if ($bm->onCost){
                    if (is_array($this->character->morphs)){
                        foreach ($this->character->morphs as $m){
                            switch ($source) {
                                case EPBonusMalus::$FROM_MORPH:
                                    $m->ratioCostMorphMod *= $bm->value;
                                break;
                                case EPBonusMalus::$FROM_TRAIT:
                                    $m->ratioCostTraitMod *= $bm->value;
                                break;  
                                case EPBonusMalus::$FROM_FACTION:
                                    $m->ratioCostFactionMod *= $bm->value;
                                break;    
                                case EPBonusMalus::$FROM_BACKGROUND:
                                    $m->ratioCostBackgroundMod *= $bm->value;
                                break;                        
                                case EPBonusMalus::$FROM_SOFTGEAR:
                                    $m->ratioCostSoftgearMod *= $bm->value;
                                break; 
                                case EPBonusMalus::$FROM_PSY:
                                    $m->ratioCostPsyMod *= $bm->value;
                                break;                        
                            }                            
                        }
                    }                                                     
                }
            break;
            case EPBonusMalus::$ON_REPUTATION_POINTS:
                 switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:
                        $this->reputationPointsMorphMod += $bm->value;
                    break;
                    case EPBonusMalus::$FROM_TRAIT:
                        $this->reputationPointsTraitMod += $bm->value;
                    break;  
                    case EPBonusMalus::$FROM_FACTION:
                        $this->reputationPointsFactionMod += $bm->value;
                    break;    
                    case EPBonusMalus::$FROM_BACKGROUND:
                        $this->reputationPointsBackgroundMod += $bm->value;
                    break;                        
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        $this->reputationPointsSoftGearMod += $bm->value;
                    break; 
                    case EPBonusMalus::$FROM_PSY:
                        $this->reputationPointsPsyMod += $bm->value;
                    break;                        
                }                                   
            break;
            case EPBonusMalus::$ON_REPUTATION_MAX:
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->maxValueMorphMod += $bm->value;
                            }
                        }                        
                    break;
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->maxValueTraitMod += $bm->value;
                            }
                        }                        
                    break;
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->maxValueFactionMod += $bm->value;
                            }
                        }                        
                    break; 
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->maxValueBackgroundMod += $bm->value;
                            }
                        }                        
                    break;                
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->maxValueSoftgearMod += $bm->value;
                            }
                        }                        
                    break;     
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->maxValuePsyMod += $bm->value;
                            }
                        }                        
                    break;                     
                }                 
            break;
            case EPBonusMalus::$ON_REPUTATION_ABSOLUTE:
                switch ($source) {
                    case EPBonusMalus::$FROM_MORPH:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->absoluteValueMorphMod = $bm->value;
                            }
                        }                        
                    break;
                    case EPBonusMalus::$FROM_TRAIT:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->absoluteValueTraitMod = $bm->value;
                            }
                        }                        
                    break;
                    case EPBonusMalus::$FROM_FACTION:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->absoluteValueFactionMod = $bm->value;
                            }
                        }                        
                    break; 
                    case EPBonusMalus::$FROM_BACKGROUND:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->absoluteValueBackgroundMod = $bm->value;
                            }
                        }                        
                    break;                
                    case EPBonusMalus::$FROM_SOFTGEAR:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->absoluteValueSoftgearMod = $bm->value;
                            }
                        }                        
                    break;     
                    case EPBonusMalus::$FROM_PSY:
                        foreach ($this->character->ego->reputations as $r){
                            if (strcmp($r->name,$bm->forTargetNamed) == 0 || EPAtom::isInGroups($r,$bm->groups)){
                                $r->absoluteValuePsyMod = $bm->value;
                            }
                        }                        
                    break;                     
                }                 
            break;
            case EPBonusMalus::$ON_IMPLANT:
                foreach ($this->character->morphs as $m) {
                    if ($this->morphHaveBonusMalus($bm,$m)){ 
                        switch ($source) {
                            case EPBonusMalus::$FROM_MORPH:
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,EPGear::$IMPLANT_GEAR) == 0){
                                        if ($bm->onCost == 'true'){
                                            $g->ratioCostMorphMod *= $bm->value;
                                        }                                
                                    }                        
                                }                        
                            break;                           
                            case EPBonusMalus::$FROM_TRAIT:
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,EPGear::$IMPLANT_GEAR) == 0){
                                        if ($bm->onCost == 'true'){
                                            $g->ratioCostTraitMod *= $bm->value;
                                        }                               
                                    }                        
                                }                            
                            break;
                            case EPBonusMalus::$FROM_FACTION:
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,EPGear::$IMPLANT_GEAR) == 0){
                                        if ($bm->onCost == 'true'){
                                            $g->ratioCostFactionMod *= $bm->value;
                                        }                               
                                    }                        
                                }                            
                            break;    
                            case EPBonusMalus::$FROM_BACKGROUND:
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,EPGear::$IMPLANT_GEAR) == 0){
                                        if ($bm->onCost == 'true'){
                                            $g->ratioCostBackgroundMod *= $bm->value;
                                        }                             
                                    }                        
                                }                            
                            break;
                            case EPBonusMalus::$FROM_SOFTGEAR:
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,EPGear::$IMPLANT_GEAR) == 0){
                                        if ($bm->onCost == 'true'){
                                            $g->ratioCostSoftgearMod *= $bm->value;
                                        }                               
                                    }                        
                                }                            
                            break;
                            case EPBonusMalus::$FROM_PSY:
                                foreach ($m->additionalGears as $g) {
                                    if (strcmp($g->gearType,EPGear::$IMPLANT_GEAR) == 0){
                                        if ($bm->onCost == 'true'){
                                            $g->ratioCostPsyMod *= $bm->value;
                                        }                                
                                    }                        
                                }                            
                            break;                    
                        }                          
                    }                    
                }
            break;            
            case EPBonusMalus::$MULTIPLE:
                if (is_array($bm->bonusMalusTypes)){
                    foreach ($bm->bonusMalusTypes as $b){
                        if ($b->selected){
                            $this->applyBonusMalus($b,$source);
                        }
                    }
                }
            break;
        } 
    }
    function morphHaveBonusMalus($bm,$m){
        foreach ($m->traits as $t) {
            foreach ($t->bonusMalus as $b) {
                if (strcmp($b->name,$bm->name) == 0){
                    return true;
                }                
            }
        }
        foreach ($m->additionalTraits as $t) {
            foreach ($t->bonusMalus as $b) {
                if (strcmp($b->name,$bm->name) == 0){
                    return true;
                }                
            }
        }        
    }
    //HELPERS
    function removeLastWord($name){
	    $splitName = mb_split(" ",$name);
		 array_pop($splitName);
		 $wLastWord = "";
		 foreach($splitName as $s){
		 	$wLastWord .= $s;
		 	$wLastWord .= " ";
		 }
		 return $wLastWord;
	}
	
	function getMorphGrantedBMApptitudesNameList($morph){
		$aptNameList = array();
		foreach($morph->bonusMalus as $bm){
			if($bm->bonusMalusType == EPBonusMalus::$ON_APTITUDE){
				if(!empty($bm->forTargetNamed)){
					array_push($aptNameList, $bm->forTargetNamed);
				}
			}
		}
		return $aptNameList;
	}
}
