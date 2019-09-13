package org.oxzion.camunda


import org.camunda.bpm.engine.BadUserRequestException
import org.camunda.bpm.engine.authorization.Groups
import org.camunda.bpm.engine.identity.GroupQuery
import org.camunda.bpm.engine.identity.NativeUserQuery
import org.camunda.bpm.engine.identity.TenantQuery
import org.camunda.bpm.engine.identity.UserQuery
import org.camunda.bpm.engine.impl.context.Context
import org.camunda.bpm.engine.impl.identity.ReadOnlyIdentityProvider
import org.camunda.bpm.engine.impl.interceptor.CommandContext

import java.sql.Connection
import java.sql.DriverManager
import java.sql.ResultSet
import java.sql.Statement

class IdentityProvider implements ReadOnlyIdentityProvider  {
    def BASE_DB_URL = System.getenv('BASE_DB_URL')
    def DB_DRIVER = System.getenv('DB_DRIVER')
    def DB_USERNAME = System.getenv('DB_USERNAME')
    def DB_PASSWORD = System.getenv('DB_PASSWORD')

    @Override
    org.camunda.bpm.engine.identity.User findUserById(String userId) {Class.forName(DB_DRIVER).newInstance()
        Connection con = DriverManager.getConnection(BASE_DB_URL, DB_USERNAME, DB_PASSWORD);
        Statement st = con.createStatement()
        String statement = 'select username,firstname,lastname,email,password from ox_user where username="'+userId+'"'
        ResultSet rs = st.executeQuery(statement)
        if(rs.next()) {
            Context.getCommandContext().disableAuthorizationCheck()
            Context.getCommandContext().authorizationManager.checkCamundaAdmin()
            return new User(rs.getString("username"),rs.getString("email"),rs.getString("firstname"),rs.getString("lastname"),rs.getString("password"))
        }
    }


    @Override
    UserQuery createUserQuery() {
        return new UserQueryImpl(Context.getProcessEngineConfiguration().getCommandExecutorTxRequired())
    }

    @Override
    UserQuery createUserQuery(CommandContext commandContext) {
        return new UserQueryImpl()
    }

    long findUserCountByQueryCriteria(UserQueryImpl query) {
        return findUserByQueryCriteria(query).size()
    }

    long findGroupCountByQueryCriteria(GroupQueryImpl query) {
        return findGroupByQueryCriteria(query).size()
    }
    long findTenantCountByQueryCriteria(TenantQueryImpl query) {
        return findTenantByQueryCriteria(query).size()
    }

    List<Group> findGroupByQueryCriteria(GroupQueryImpl query) {
        Class.forName(DB_DRIVER).newInstance()
        Connection con = DriverManager.getConnection(BASE_DB_URL, DB_USERNAME, DB_PASSWORD);
        Statement st = con.createStatement()
        String statement = "select id,name,status from ox_group"
        String orderByPart = ""
        if(query.orderByGroupId())
            orderByPart = "ORDER BY ox_group.id"
        if(query.orderByGroupName())
            orderByPart = "ORDER BY ox_group.name"
        if(query.orderByGroupType())
            orderByPart = "ORDER BY ox_group.status"
        if(query.getId() != null)
            statement = "select id,name,status from ox_group where id='${query.getId()}' ${orderByPart}"
        if(query.getIds() != null)
            statement = "select id,name,status from ox_group where id IN ('"+String.join("','", query.getIds())+"') ${orderByPart}"
        if(query.getName() != null)
            statement = "select id,name,status from ox_group where name ='${query.getName()}' ${orderByPart}"
        if(query.getName() != null)
            statement = "select id,name,status from ox_group where name like '%${query.getName()}%' ${orderByPart}"
        if(query.getType() != null)
            statement = "select id,name,status from ox_group where status like '%${query.getType()}%' ${orderByPart}"
        if(query.getTenantId() != null || query.tenantId)
            statement = "select id,name,status from ox_group where orgid = '${query.getTenantId()}' ${orderByPart}"
        if(query.getUserId() !=null)
            statement = "select ox_group.id,ox_group.name,ox_group.status FROM ox_group LEFT JOIN ox_user_group ON ox_group.id=ox_user_group.group_id WHERE ox_user_group.avatar_id='"+query.getUserId()+"' "+orderByPart
        print statement
        ResultSet rs = st.executeQuery(statement)
        ArrayList<Group> groups =  new ArrayList<Group>()
        while (rs.next()) {
            groups.add(new Group(rs.getString("id"), rs.getString("name"), "SYSTEM"))
        }
        groups.add(new Group("99999",'admin', Groups.CAMUNDA_ADMIN))
        return groups
    }

    @Override
    NativeUserQuery createNativeUserQuery() {
        throw new BadUserRequestException("not supported")
    }

    @Override
    boolean checkPassword(String userId, String password) {
        if(password == null) {
            return false
        }
        // engine can't work without users
        if(userId == null || userId.isEmpty()) {
            return false
        }
        return true
    }


    @Override
    org.camunda.bpm.engine.identity.Group findGroupById(String groupId) {
        Class.forName(DB_DRIVER).newInstance()
        Connection con = DriverManager.getConnection(BASE_DB_URL, DB_USERNAME, DB_PASSWORD);
        Statement st = con.createStatement()
        String statement = "select id,name,status from ox_group"
        ResultSet rs = st.executeQuery(statement)
        if(rs.next()) {
            return new Group(rs.getString("id"),rs.getString("name"),rs.getString("status"))
        } else {
            return new Group("99999",'admin', Groups.CAMUNDA_ADMIN)
        }
    }

    @Override
    GroupQuery createGroupQuery() {
        return new GroupQueryImpl(Context.getProcessEngineConfiguration().getCommandExecutorTxRequired());
    }

    @Override
    GroupQuery createGroupQuery(CommandContext commandContext) {
        return new GroupQueryImpl()
    }
    List<User> findUserByQueryCriteria(UserQueryImpl query) {
        Class.forName(DB_DRIVER).newInstance()
        Connection con = DriverManager.getConnection(BASE_DB_URL, DB_USERNAME, DB_PASSWORD);
        Statement st = con.createStatement()
        try {
            String statement = "select username,firstname,lastname,email,password from ox_user"
            String orderByPart = ""
            if(query.orderingProperties){
                if(query.orderByUserEmail())
                    orderByPart = "ORDER BY ox_user.email"
                if(query.orderByUserFirstName())
                    orderByPart = "ORDER BY ox_user.firstname"
                if(query.orderByUserLastName())
                    orderByPart = "ORDER BY ox_user.lastname"
                if(query.orderByUserId())
                    orderByPart = "ORDER BY ox_user.username"
            }
            if(query.getId() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where username='${query.getId()}' ${orderByPart}"
            if(query.getIds() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where username in ('"+String.join("','", query.getIds())+"') ${orderByPart}"
            if(query.getFirstName() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where firstname='${query.getFirstName()}' ${orderByPart}"
            if(query.getFirstNameLike() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where firstname like'%${query.getFirstName()}%' ${orderByPart}"
            if(query.getLastName() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where lastname='${query.getLastName()}' ${orderByPart}"
            if(query.getLastNameLike() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where lastname like'%${query.getLastName()}%' ${orderByPart}"
            if(query.getEmail() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where email='${query.getEmail()}' ${orderByPart}"
            if(query.getEmailLike() != null)
                statement = "select username,firstname,lastname,email,password from ox_user where email like '%${query.getEmail()}%' ${orderByPart}"
            if(query.getGroupId() != null)
                statement = "select username,firstname,lastname,email,password FROM ox_user LEFT JOIN ox_user_group ON ox_user.id=ox_user_group.avatar_id WHERE ox_user_group.group_id=${query.getGroupId()} ${orderByPart}"
            if(query.getTenantId() != null)
                statement = "select username,firstname,lastname,email,password FROM ox_user WHERE orgid='${query.getTenantId()}' ${orderByPart}"
            ResultSet rs = st.executeQuery(statement)
            ArrayList<User> users = new ArrayList<User>()
            while(rs.next()) {
                users.add(new User(rs.getString("username"),rs.getString("email"),rs.getString("firstname"),rs.getString("lastname"),rs.getString("password")))
            }
            return users
        } catch(Exception e){
            print(e.getMessage())
        }
        return null
    }
    List<Tenant> findTenantByQueryCriteria(TenantQueryImpl query) {
        Class.forName(DB_DRIVER).newInstance()
        Connection con = DriverManager.getConnection(BASE_DB_URL, DB_USERNAME, DB_PASSWORD);
        Statement st = con.createStatement()
        String orderByPart = "ORDER BY ox_organization.id"
        if(query.orderByTenantId())
            orderByPart = "ORDER BY ox_organization.id"
        if(query.orderByTenantName())
            orderByPart = "ORDER BY ox_organization.name"
        String statement = "select id,name from ox_organization"
        if(query.getIds())
            statement = "select id,name from ox_organization where id IN ('"+String.join("','", query.getIds())+"') ${orderByPart}"
        if(query.getId() != null)
            statement = "select id,name from ox_organization where id=${query.getId()} ${orderByPart}"
        if(query.getName() != null)
            statement = "select id,name from ox_organization where name='${query.getName()}' ${orderByPart}"
        if(query.getNameLike() != null)
            statement = "select id,name from ox_organization where name like'%${query.getNameLike()}' ${orderByPart}"
        ResultSet rs = st.executeQuery(statement)
        ArrayList<Tenant> tenants = new ArrayList<Tenant>()
        while (rs.next()) {
            tenants.add(new Tenant(rs.getString("id"),rs.getString("name")))
        }
        return tenants
    }

    @Override
    org.camunda.bpm.engine.identity.Tenant findTenantById(String tenantId) {
        Class.forName(DB_DRIVER).newInstance()
        Connection con = DriverManager.getConnection(BASE_DB_URL, DB_USERNAME, DB_PASSWORD)
        Statement st = con.createStatement()
        String statement = "select id,name from ox_organization where id = '"+tenantId+"'"
        ResultSet rs = st.executeQuery(statement)
        if(rs.next()) {
            return new Tenant(rs.getString("id"),rs.getString("name"))
        }
    }

    @Override
    TenantQuery createTenantQuery() {
        return new TenantQueryImpl(Context.getProcessEngineConfiguration().getCommandExecutorTxRequired());
    }

    @Override
    TenantQuery createTenantQuery(CommandContext commandContext) {
        return new TenantQueryImpl()
    }

    @Override
    void flush() {

    }

    @Override
    void close() {

    }
}
