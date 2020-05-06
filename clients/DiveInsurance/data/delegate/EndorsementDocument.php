<?php


require_once __DIR__."/PolicyDocument.php";

class EndorsementDocument extends PolicyDocument
{
    public function __construct(){
        parent::__construct();
        $this->type = 'endorsement';
        $this->template = array(
            'Dive Boat' 
            => array(
             'cover_letter' => 'Dive_Boat_Quote_Cover_Letter',
             'lheader' => 'letter_header.html',
             'lfooter' => 'letter_footer.html',
             'template' => 'DiveBoat_Endorsement',
             'header' => 'DB_Endorsement_header.html',
             'footer' => 'DB_Endorsement_footer.html',
             'aniTemplate' => 'DiveBoat_ANI',
             'aniheader' => 'DB_Quote_ANI_header.html',
             'anifooter' => null,
             'policy' => 'Dive_Boat_Policy.pdf',
             'gtemplate' => 'Group_PL_COI',
             'gheader' => 'Group_EndoHeader.html',
             'gfooter' => 'Group_footer.html',
             'nTemplate' => 'Group_PL_NI',
             'nheader' => 'Group_Endo_NI_header.html',
             'nfooter' => 'Group_NI_footer.html',
             'lpTemplate' => 'DiveBoat_LP',
             'lpheader' => 'DiveBoat_LP_header.html',
             'lpfooter' => 'DiveBoat_LP_footer.html',
             'waterEndorsement' => 'DB_In_Water_Crew_Endorsement.pdf',
             'blanketForm' => 'DB_AI_Blanket_Endorsement.pdf',
             'groupExclusions' => 'Group_Exclusions.pdf'),
            'Dive Store' 
            => array(
             'template' => 'DiveStoreEndorsement',
             'header' => 'DiveStoreEndorsement_header.html',
             'footer' => 'DiveStoreEndorsement_footer.html', 
             'cover_letter' => 'Dive_Store_Cover_Letter',
             'lheader' => 'letter_header.html',
             'lfooter' => 'letter_footer.html',
             'policy' => array('liability' => 'Dive_Store_Liability_Policy.pdf','property' => 'Dive_Store_Property_Policy.pdf'),
             'lpTemplate' => 'DiveStore_LP',
             'lpheader' => 'DiveStore_LP_header.html',
             'lpfooter' => 'DiveStore_LP_footer.html',
             'alheader' => 'DiveStore_AL_header.html',
             'alfooter' => 'DiveStore_AL_footer.html',
             'aniTemplate' => 'DiveStore_ANI',
             'aniheader' => 'DS_Quote_ANI_header.html',
             'anifooter' => null,
             'alTemplate' => 'DiveStore_AdditionalLocations',
             'gtemplate' => 'Group_PL_COI_DS',
             'gheader' => 'Group_EndoHeader.html',
             'gfooter' => 'Group_footer.html',
             'nTemplate' => 'Group_PL_NI',
             'nheader' => 'Group_Endo_NI_header.html',
             'nfooter' => 'Group_NI_footer.html',
             'blanketForm' => 'DS_AI_Blanket_Endorsement.pdf',
             'travelAgentEO' => 'Travel_Agents_PL_Endorsement.pdf',
             'groupExclusions' => 'Group_Exclusions.pdf',
             'AutoLiability'=>'DS_NonOwned_Auto_Liability.pdf'
         ));        
    }
}