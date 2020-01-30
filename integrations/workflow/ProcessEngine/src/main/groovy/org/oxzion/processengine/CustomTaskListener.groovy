package org.oxzion.processengine

import groovy.json.JsonBuilder
import org.camunda.bpm.engine.delegate.DelegateTask
import org.camunda.bpm.engine.delegate.TaskListener
import org.camunda.bpm.engine.task.IdentityLink

import java.text.SimpleDateFormat
import org.slf4j.Logger
import org.slf4j.LoggerFactory
  


class CustomTaskListener implements TaskListener {
  private static final Logger logger = LoggerFactory.getLogger(CustomTaskListener.class);

  private static CustomTaskListener instance = null

  protected CustomTaskListener() { }

  static CustomTaskListener getInstance() {
    if(instance == null) {
      instance = new CustomTaskListener()
    }
    return instance
  }
  def getConnection(){
    String url = getConfig()
    def baseUrl = new URL("${url}/callback/workflow/activityinstance")
    logger.info("Opening connection to ${baseUrl}")
    return baseUrl.openConnection()
  }

  void notify(DelegateTask delegateTask) {
    Map taskDetails = [:]
    taskDetails.name = delegateTask.name
    def candidatesArray = []
    def i=0
    for (IdentityLink item : delegateTask.getCandidates()){
      Map candidateList = [:]
      candidateList.groupid = item.getGroupId()
      candidateList.type = item.getType()
      candidateList.userid = item.getUserId()
      candidatesArray[i] = candidateList
      i++
    }
    taskDetails.candidates = candidatesArray
    taskDetails.owner = delegateTask.getOwner()
    taskDetails.assignee = delegateTask.getAssignee()
    taskDetails.status = "in_progress"
    taskDetails.taskId = delegateTask.getTaskDefinitionKey()
    String pattern = "dd-MM-yyyy"
    SimpleDateFormat simpleCreateDateFormat = new SimpleDateFormat(pattern)
    taskDetails.createTime = simpleCreateDateFormat.format(delegateTask.createTime)
    taskDetails.dueDate = delegateTask.dueDate ? simpleCreateDateFormat.format(delegateTask.dueDate) : delegateTask.dueDate
    taskDetails.executionId = delegateTask.getExecutionId()
    def execution = delegateTask.execution
    def processInstance = execution.getProcessInstance()
    taskDetails.processVariables = processInstance.getVariables()
    taskDetails.activityInstanceId = delegateTask.getId()
    taskDetails.executionActivityinstanceId = execution.activityInstanceId
    taskDetails.processInstanceId = execution.processInstanceId
    taskDetails.variables = execution.getVariables()
    taskDetails.parentActivity = execution.getParentActivityInstanceId()
    taskDetails.currentActivity = execution.getCurrentActivityId()
    taskDetails.parent = execution.getParentId()
    String json = new JsonBuilder(taskDetails ).toPrettyString()
    logger.info("Posting data - ${json}")
    def connection = getConnection()
    String response
    connection.with {
      doOutput = true
      requestMethod = 'POST'
      outputStream.withWriter { writer ->
        writer << json
      }
      response = inputStream.withReader{ reader ->
        reader.text
      }
      logger.info("Response received - ${response}")
    }
  }
  private def getConfig(){
    def properties = new Properties()
    this.getClass().getResource( '/application.properties' ).withInputStream {
      properties.load(it)
    }
    return properties."applicationurl"
  }
}
