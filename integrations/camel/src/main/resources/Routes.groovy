callback.URL = 'http4://localhost:8080'

routes {
    route = [
        ['from':'activemq:topic:ORGANIZATION_ADDED', 'to':["${callback.URL}/callback/chat/addorg"]],
        ['from':'activemq:topic:ORGANIZATION_UPDATED', 'to':["${callback.URL}/callback/chat/updateorg",
                                                             "${callback.URL}/callback/crm/updateorg"]],
        ['from':'activemq:topic:ORGANIZATION_DELETED', 'to':["${callback.URL}/callback/chat/deleteorg",
                                                             "${callback.URL}/callback/crm/deleteorg"]],
        ['from':'activemq:topic:USERTOORGANIZATION_ADDED', 'to':["${callback.URL}/callback/chat/adduser",
                                                                 "${callback.URL}/callback/crm/adduser"]],
        // ['from':'activemq:topic:USERTOORGANIZATION_ALREADYEXISTS', 'to':'"callback.URL" + '],

        ['from':'activemq:topic:PROJECT_ADDED', 'to':["${callback.URL}/callback/chat/createchannel"]],
        ['from':'activemq:topic:PROJECT_UPDATED', 'to':["${callback.URL}/callback/chat/updatechannel"]],
        ['from':'activemq:topic:PROJECT_DELETED', 'to':["${callback.URL}/callback/chat/deletechannel"]],
        ['from':'activemq:topic:USERTOPROJECT_ADDED', 'to':["${callback.URL}/callback/chat/addusertochannel"]],
        ['from':'activemq:topic:USERTOPROJECT_DELETED', 'to':["${callback.URL}/callback/chat/removeuserfromchannel"]],

        ['from':'activemq:topic:GROUP_ADDED', 'to':["${callback.URL}/callback/chat/createchannel"]],
        ['from':'activemq:topic:GROUP_UPDATED', 'to':["${callback.URL}/callback/chat/updatechannel"]],
        ['from':'activemq:topic:GROUP_DELETED', 'to':["${callback.URL}/callback/chat/deletechannel"]],
        ['from':'activemq:topic:USERTOGROUP_ADDED', 'to':["${callback.URL}/callback/chat/addusertochannel"]],
        ['from':'activemq:topic:USERTOGROUP_DELETED', 'to':["${callback.URL}/callback/chat/removeuserfromchannel"]],

        // ['from':'activemq:topic:USER_ADDED', 'to':["${callback.URL}"]],
        // ['from':'activemq:topic:USER_DELETED', 'to':["${callback.URL}"]]
    ]
}
