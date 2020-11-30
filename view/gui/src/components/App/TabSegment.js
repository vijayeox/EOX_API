import React from "react";
import JsxParser from "react-jsx-parser";
import moment from "moment";
import PageContent from "./PageContent";
import { Tabs, TabLink, TabContent } from "react-tabs-redux";

const styles = {
  tabs: {
    width: "100%",
    position: "relative",
    display: "-ms-flexbox",
    display: "flex",
    flexDirection: "column",
    minWidth: "0",
    wordWrap: "break-word",
    backgroundColor: "#fff",
    backgroundClip: "border-box",
    border: "1px solid rgba(0,0,0,.125)",
    borderRadius: ".25rem"
  },
  links: {
    padding: ".75rem 1.25rem",
    paddingBottom: "0",
    marginBottom: "0",
    backgroundColor: "rgba(0,0,0,.03)",
    borderBottom: "1px solid rgba(0,0,0,.125)"
  },
  visibleTabStyle: {
    display: "block"
  }
};

class TabSegment extends React.Component {
  constructor(props) {
    super(props);
    this.core = this.props.core;
    this.proc = this.props.proc;
    this.profileAdapter = this.core.make("oxzion/profile");
    this.profile = this.profileAdapter.get();
    this.appId = this.props.appId;
    this.pageId = this.props.pageId;
    this.tabs = this.props.tabs;
    this.currentRow = this.props.currentRow;
    this.state = {
      content: this.props.content,
      pageContent: [],
      dataReady: false,
      currentRow: this.props.currentRow?this.props.currentRow:{},
      tabNames: [],
      tabContent: [],
      tabs: this.props.tabs?this.props.tabs:[]
    };
    if(this.props.tabs.length > 1){
      var tabNames = [];
      var tabContent = []
      this.props.tabs.map((item, i) => {
        tabNames.push(<TabLink to={item.uuid}> {item.name}</TabLink>);
        var tabContentKey = item.uuid+'_tab';
        tabContent.push(<TabContent for={item.uuid}>
        <PageContent
          key={tabContentKey}
          config={this.props.config}
          proc={this.props.proc}
          isTab="true"
          appId={this.props.appId}
          parentPage={this.pageId}
          currentRow={this.state.currentRow}
          pageContent={item.content}
          pageId={item.pageId}
          fileId={this.uuid}
          core={this.core}
        />
            </TabContent>)
      });
      this.state.tabNames= tabNames;
      this.state.tabContent= tabContent;
      this.state.dataReady= true;
    }
  }
  componentDidUpdate(prevProps){
    if(prevProps.tabs !== this.props.tabs){
      this.setState({tabs:this.props.tabs});
      if(this.props.tabs.length > 1){
        var tabNames = [];
        var tabContent = []
        this.props.tabs.map((item, i) => {
          tabNames.push(<TabLink to={item.uuid} style={styles.tabLink}> {item.name}</TabLink>);
          var tabContentKey = item.uuid+'_tab';
          tabContent.push(<TabContent for="uuid" key={uuid}>
          <PageContent
            key={tabContentKey}
            config={this.props.config}
            proc={this.props.proc}
            isTab="true"
            appId={this.props.appId}
            parentPage={this.pageId}
            pageContent={item.content}
            pageId={this.pageId}
            fileId={this.uuid}
            currentRow={this.state.currentRow}
            core={this.core}
          />
              </TabContent>)
        });
        this.setState({tabNames: tabNames});
        this.setState({tabContent: tabContent});
      }
    }
  }

  render() {
    if (
      this.state.tabs &&
      this.state.tabs.length == 1
    ) {
        return (<PageContent
          key={this.state.tabs[0].uuid}
          config={this.props.config}
          proc={this.props.proc}
          appId={this.props.appId}
          fileId={this.uuid}
          pageContent={this.state.tabs[0].content?this.state.tabs[0].content:null}
          currentRow={this.state.currentRow}
          core={this.core}
        />);
  } else if(this.state.tabs &&
  this.state.dataReady){
    return (<Tabs
              name="tabs2"
              selectedTab={this.state.tabs[0].uuid}
              visibleTabStyle={styles.visibleTabStyle}
              style={styles.tabs}
            >
          <div style={styles.links}>
{this.state.tabNames}
                    </div>
          <div className="tabContentDiv">
{this.state.tabContent}
          </div>
            </Tabs>);
  } else {
      return <div>No Content to Display</div>;
  }
}
}

export default TabSegment;
