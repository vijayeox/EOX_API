<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/divebtemplate_css.css" rel="stylesheet" type="text/css" />
</head>
<body>
	<div class ="body_div_endo">
	     <div class = "section1">
          <div class = "sectiona">
            <p class="hull_title">Name of Vessel: {$vessel_name}</p>
            <p class="hull_title"><span>Year Built: &nbsp&nbsp&nbsp&nbsp {$built_year}&nbsp&nbsp&nbsp</span><span>Length:&nbsp&nbsp&nbsp&nbsp{$vessel_length}&nbsp&nbsp&nbsp</span><span>HP:&nbsp&nbsp&nbsp&nbsp{$vessel_hp}</span></p>
            <p class="hull_title">S/N: &nbsp&nbsp{$vessel_sno}</p>
          </div>
          <div class = "sectionb">
            <p class="hull_title">Hull Type: {$hull_type}</p>
            <p class="hull_title">Mfg: &nbsp&nbsp{$hull_mfg}</p>  
          </div>
          <hr></hr>
         {if isset($layup_period_from_date_time)}
          <hr></hr>
            Layup Period is from {$layup_period_from_date_time|date_format:"%d %B %Y"} to {$layup_period_to_date_time|date_format:"%d %B %Y"}
          <hr></hr>
         {/if}




         {if isset($increased_hullValue) || isset($decreased_hullValue) || isset($increased_dinghyValue) || isset($decreased_dinghyValue) || isset($increased_trailerValue) || isset($decreased_trailerValue)}
          <p>*** SECTION A - PROPERTY INSURED - CHANGE(S)</p>
          <p>{if isset($increased_hullValue)}
                  *** The Agreed Valuation of said Vessel has increased by ${$increased_hullValue},the deductible has increased by ${$increased_deductible},the premium has increased by ${$increased_hullPremium}
            {else if isset($decreased_hullValue)}
                  *** The Agreed Valuation of said Vessel has been decreased by ${$decreased_hullValue},the premium has been decreased by ${$decreased_hullPremium}
            {/if}
          </p>


          <p>{if isset($increased_dinghyValue)}
                *** The amount of DinghyTender Insurance has increased by ${$increased_dinghyValue},the premium has increased by ${$increased_dinghyPremium}
             {else if isset($decreased_dinghyValue)}
                *** The amount of DinghyTender Insurance has been decreased by ${$decreased_dinghyValue},the premium has been decreased by ${$decreased_dinghyValue}
              {/if}
          </p>


          <p>{if isset($increased_trailerValue)}
               *** The amount of Trailer Insurance has increased by ${$increased_trailerValue} and the premium has increased by ${$increased_trailerPremium}
             {else if isset($decreased_trailerValue)}
               *** The amount of Trailer Insurance has been decreased by ${$decreased_trailerValue} and the premium has been decreased by ${$decreased_trailerPremium}
             {/if}
          </p>
        {/if}             


        {if isset($increased_totalLiabilityLimitValue) || isset($decreased_totalLiabilityLimitValue) || isset($increased_passengers) || isset($decreased_passengers) || isset($increased_crewInBoat) || isset($decreased_crewInBoat) || isset($increased_crewInWater) || isset($decreased_crewInWater)}
          <p>*** SECTION B - LIABILITY INSURED - CHANGE(S)</p>
          <p>{if isset($increased_totalLiabilityLimitValue)}
            *** The Liability limit has now been increased by ${$increased_totalLiabilityLimitValue},with the deductible has increased by ${$liability_deductible}
          {else if isset($decreased_totalLiabilityLimitValue)}
            *** The Liability limit has now been decreased by ${$decreased_totalLiabilityLimitValue}
          {/if}</p>

          <p>
            {if isset($increased_passengers)}
             *** Limit of Insurance - Passenger Liability: {$increased_passengers} Passenger(s) have been added to this certificate.
            {else if isset($decreased_passengers)}
              *** Limit of Insurance - Passenger Liability: {$decreased_passengers} Passenger(s) have been deleted from this certificate.
            {/if}
          </p>



         
           {if isset($increased_crewInBoat) || isset($decreased_crewInBoat) || isset($increased_crewInWater) || isset($decreased_crewInWater)} 
             <p>*** Limit of Insurance - Passenger Liability: Has now been added to this certificate. The Liability limit is now ${$primaryLimit}(plus any Excess Liability already purchased) with a dedcutible of ${$liability_deductible}
            </p>

            <p>{if isset($increased_crewInBoat)}
              *** {$increased_crewInBoat} Crew on Boat has been added to this certificate with an additional premium of ${$increased_crewInBoatPremium}
              {else if isset($decreased_crewInBoat)}
              *** {$decreased_crewInBoat} Crew ob Boat has been deleted from this certificate,the premium has been decreased by ${$decreased_crewInBoatPremium}
              {/if}
            </p>
          

            <p>
            {if isset($increased_crewInWater)}
             *** {$increased_crewInWater} Crew on Water has been added to this certificate with an additional premium of ${$increased_crewInWaterPremium}
              {else if isset($decreased_crewInWater)}
              *** {$decreased_crewInWater} Crew ob Boat has been deleted from this certificate,the premium has been decreased by ${$decreased_crewInWaterPremium}
            {/if}
          </p>
          {/if}
        {/if}


            {if isset($additionalInsured) && $additional_insured_select == 'newListOfAdditionalInsureds'}
            {assign var=list value=$additionalInsured|json_decode:true}

            <p>Name & Address</p>
                <center> 
                    <p>
                    Not withstanding the fact that such parties as advised are hereby named in their capacity as advised as Co-Assured in this Policy, this cover will only extend insofar as they may be found liable to pay in the first instance for liabilities which are properly the responsibility of the Assured, and nothing herein contained shall be construed as extending cover in respect of any amount which would not have been recoverable hereunder by the Assured had such claim been made or enforced against him. Once indemnification hereunder has been made there shall be no further liability hereunder to make any further payment to any person or company whatsoever, including the Assured, in respect of that claim.
                    </p>

                    <p>
                    All rights granted to us together with all duties of an assured under the original insuring agreement shall also apply to any other named co-assured jointly.
                    </p>
                </center>

                {foreach from=$list item=$additional}
                   {$additional.name},
                {/foreach}
            {/if}
          </p>
        </div>
	</div>
</body>
</html>

