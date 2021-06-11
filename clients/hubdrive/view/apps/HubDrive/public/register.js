import React from 'react';
import ReactDOM from 'react-dom';
import BaseFormRenderer from '../../../../view/gui/src/components/App/BaseFormRenderer'
// import BaseFormRenderer from 'oxziongui'
import form from '../../../../content/forms/onboarding.json'
class Register extends React.Component {
    constructor(props){
        super(props);
        this.core = this.props.core;
        this.state = {
          isMenuOpen: false,
          sectionData: null,
          title: ''
        };
    }
  render() {
    return <BaseFormRenderer
      key={'asdasf'}
    //   url={item.url == '' ? undefined: dataString}
    //   urlPostParams={urlPostParams}
    //   appId={this.appId}
    //   postSubmitCallback={this.postSubmitCallback}
    //   data={item.data}
      content={form}
    //   fileId={fileId}
    //   formId={item.form_id}
    //   page={item.page}
    //   pipeline={item.pipeline}
    //   workflowId={workflowId}
    //   cacheId={cacheId}
    //   isDraft={item.isDraft}
    //   activityInstanceId={activityInstanceId}
    //   parentWorkflowInstanceId={workflowInstanceId}
    //   dataUrl={item.dataUrl ? this.prepareDataRoute(item.dataUrl, this.state.currentRow,true) : undefined}
    />
  }
}

ReactDOM.render(<Register/>, document.getElementById('root'));