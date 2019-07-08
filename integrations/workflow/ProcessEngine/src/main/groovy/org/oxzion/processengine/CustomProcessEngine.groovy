package org.oxzion.processengine

import org.camunda.bpm.engine.impl.bpmn.behavior.UserTaskActivityBehavior
import org.camunda.bpm.engine.impl.bpmn.parser.AbstractBpmnParseListener
import org.camunda.bpm.engine.impl.pvm.delegate.ActivityBehavior
import org.camunda.bpm.engine.impl.pvm.process.ActivityImpl
import org.camunda.bpm.engine.impl.pvm.process.ScopeImpl
import org.camunda.bpm.engine.impl.util.xml.Element

import java.util.logging.Logger

class CustomProcessEngine extends AbstractBpmnParseListener {
  private final Logger LOGGER = Logger.getLogger(this.getClass().getName());
     @Override
  void parseUserTask(Element userTaskElement, ScopeImpl scope, ActivityImpl activity) {
    ActivityBehavior activityBehavior = activity.getActivityBehavior()
    if(activityBehavior instanceof UserTaskActivityBehavior ){
      UserTaskActivityBehavior userTaskActivityBehavior = (UserTaskActivityBehavior) activityBehavior
      userTaskActivityBehavior.getTaskDefinition().addTaskListener("create", CustomTaskListener.getInstance())
    }
  }
}