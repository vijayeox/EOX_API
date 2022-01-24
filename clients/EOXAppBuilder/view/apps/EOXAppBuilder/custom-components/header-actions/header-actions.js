import {
    OX_Grid,
    React,
    FormRender,
    KendoReactButtons,
    PopupDialog,
  } from "oxziongui";
  export default function HeaderActions(props) {
    return (
      <Header
        core={props.core}
        appId={props.appId}
        parentPageData={props.parentPageData}
        data={props.data}
        componentProps={props.componentProps}
      />
    );
  }
  class Header extends React.Component {
    constructor(props){
        super(props);
    }
    navigate(operation){
      operation['details'][0]['params']= {app_uuid: this.props.parentPageData.uuid};
      let ev2 = new CustomEvent("clickAction", {
        detail: operation,
        bubbles: true,
      });
      document.getElementById(this.props.componentProps.contentDivID).dispatchEvent(ev2);
    }
    render(){
       return <div className="appButtons">{
        this.props.data?.params?.operations?.map((operation) => {
          return <div
                  className="moduleBtn"
                  title={operation?.name}
                  onClick={() => this.navigate(operation)}
                >
                  <div className="block"><i className={operation?.icon}></i></div>
                </div>
       })
         }</div>
    }
  }