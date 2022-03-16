Icallback.URL = "http4://localhost:8080"
routes {
    route = [
       // ['from':'activemq:topic:ORGANIZATION_ADDED', 'to':["${callback.URL}/callback/chat/addorg"]],
      //  ['from':'activemq:topic:ORGANIZATION_UPDATED', 'to':["${callback.URL}/callback/chat/updateorg"]],
       // ['from':'activemq:topic:ORGANIZATION_DELETED', 'to':["${callback.URL}/callback/chat/deleteorg"]],
       // ['from':'activemq:topic:USERTOORGANIZATION_ADDED', 'to':["${callback.URL}/callback/chat/adduser"]],
        // ['from':'activemq:topic:USERTOORGANIZATION_ALREADYEXISTS', 'to':'"callback.URL" + '],

        ['from':'activemq:topic:PROJECT_ADDED', 'to':["${callback.URL}/callback/task/addproject",
                                                      "${callback.URL}/callback/chat/createchannel"]],
        ['from':'activemq:topic:PROJECT_UPDATED', 'to':["${callback.URL}/callback/task/updateproject",
                                                        "${callback.URL}/callback/chat/updatechannel"]],
        ['from':'activemq:topic:PROJECT_DELETED', 'to':["${callback.URL}/callback/task/deleteproject",
                                                        "${callback.URL}/callback/chat/deletechannel"]],

        ['from':'activemq:topic:DELETION_USERFROMPROJECT', 'to':["${callback.URL}/callback/task/deleteuserfromtasktracker"]],
        ['from':'activemq:topic:ADDITION_USERTOPROJECT', 'to':["${callback.URL}/callback/task/addusertotasktracker"]],

        ['from':'activemq:topic:USERTOPROJECT_ADDED', 'to':["${callback.URL}/callback/chat/addusertochannel"]],
        ['from':'activemq:topic:USERTOPROJECT_DELETED', 'to':["${callback.URL}/callback/chat/removeuserfromchannel"]],

        //['from':'activemq:topic:GROUP_ADDED', 'to':["${callback.URL}/callback/task/creategroup",
          //                                          "${callback.URL}/callback/chat/createchannel"]],
       // ['from':'activemq:topic:GROUP_UPDATED', 'to':["${callback.URL}/callback/task/updategroup",
            //                                            "${callback.URL}/callback/chat/updatechannel"]],
        //['from':'activemq:topic:GROUP_DELETED', 'to':["${callback.URL}/callback/task/deletegroup",
          //                                              "${callback.URL}/callback/chat/deletechannel"]],
        //['from':'activemq:topic:USERTOGROUP_ADDED', 'to':["${callback.URL}/callback/chat/addusertochannel"]],
        //['from':'activemq:topic:USERTOGROUP_DELETED', 'to':["${callback.URL}/callback/chat/removeuserfromchannel"]],
        //['from':'activemq:topic:USERTOGROUP_UPDATED', 'to':["${callback.URL}/callback/task/updategroupusers"]],

       // ['from':'activemq:topic:ADD_CALENDAR_EVENT', 'to':["${callback.URL}/callback/calendar/addevent"]],
        ['from':'activemq:topic:USER_ADDED', 'to':["${callback.URL}/callback/ox/createuser"]],
        ['from':'activemq:topic:FILE_ADDED', 'to':["${callback.URL}/fileindexer"]],
        ['from':'activemq:topic:FILE_UPDATED', 'to':["${callback.URL}/fileindexer"]],
        ['from':'activemq:topic:FILE_DELETED', 'to':["${callback.URL}/fileindexer"]],
       // ['from':'activemq:topic:SEND_SMS', 'to':["${callback.URL}/callback/communication/sendsms"]],
       // ['from':'activemq:topic:MAKE_CALL', 'to':["${callback.URL}/callback/communication/makecall"]],
        ['from':'activemq:topic:COMMANDS', 'to':["${callback.URL}/callback/workflow/servicetask"]]
        // ['from':'activemq:topic:USER_ADDED', 'to':["${callback.URL}"]],
        // ['from':'activemq:topic:USER_DELETED', 'to':["${callback.URL}"]],
        ['from':'activemq:topic:UPDATE_CHAT_PROFILE_PICTURE', 'to':["${callback.URL}/callback/chat/updateprofilepicture"]]
    ]
}

