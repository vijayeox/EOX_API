function agentInfo(){
	data = {
	"name" : "Vicencia & Buckley A Division of HUB International Insurance Services",
	"address" : "6 Centerpointe Drive, #350 La Palma, CA 90623-2538",
	"phone1" : "(714) 739-3177",
	"phone2" : "(800) 223-9998",
	"fax" : "(714) 739-3188",
	"managerName" : "Manager Name",
	"managerEmail" : "manager@hub.com"
	};
document.getElementById('nameVal').innerHTML= data.name; 
document.getElementById('addressVal').innerHTML= data.address;
document.getElementById('phone1Val').innerHTML= data.phone1;
document.getElementById('phone2Val').innerHTML= data.phone2;
document.getElementById('faxVal').innerHTML= data.fax; 
document.getElementById('managerName').innerHTML= data.managerName; 
document.getElementById('managerEmail').innerHTML= data.managerEmail; 
}