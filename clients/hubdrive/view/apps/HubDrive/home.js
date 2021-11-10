import { React, LeftMenuTemplate, FormRender } from "oxziongui";
import { appId as application_id } from "./metadata.json";
import "./index.scss";
class Home extends React.Component {
  constructor(props) {
    super(props);
    this.core = this.props.args;
    this.helper = this.core.make("oxzion/restClient");
    this.params = this.props.params;
    this.proc = this.props.proc;
    this.state = {
      cacheID: undefined,
      formContent: undefined,
      cachePage: undefined,
      formID: undefined,
      cacheData: undefined,
      workflowId: undefined,
      showMenuPage: undefined
    };
  }

  componentDidMount() {
    this.getCacheData().then((cacheResponse) => {
      var cache = cacheResponse.data;
      if (cache) {
        if (cache.workflow_uuid) {
          this.setState({ workflowId: cache.workflow_uuid });
          this.getWorkflowForm(cache.workflow_uuid).then((formResponse) => {
            if (formResponse.data) {
              this.setState({
                formContent: JSON.parse(formResponse.data.template),
                cachePage: cache.page,
                formID: formResponse.data.id,
                cacheData: cache
              });
            } else {
              this.setState({
                showMenuPage: true
              });
            }
          });
        } else if(cache.formId) {
          this.getFormData(cache.formId).then((formResponse) => {
            if (formResponse.data) {
              this.setState({
                formContent: JSON.parse(formResponse.data.template),
                cachePage: 0,
                formID: formResponse.data.uuid,
                cacheData: cache
              });
            } else {
              this.setState({
                showMenuPage: true
              });
            }
          });
        } else {
          this.setState({
            showMenuPage: true
          });
        }
      } else {
        this.setState({
          showMenuPage: true
        });
      }
    });
  }

  async getCacheData() {
    let cacheData = await this.helper.request(
      "v1",
      "/app/" + application_id + "/cache",
      {},
      "get"
    );
    return cacheData;
  }

  async deleteCacheData() {
    let cacheData = await this.helper.request(
      "v1",
      "/app/" + application_id + "/deletecache",
      {},
      "delete"
    );
    return cacheData;
  }

  async updateCacheData() {
    let cacheData = await this.helper.request(
      "v1",
      "/app/" + application_id + "/deletecache",
      {},
      "delete"
    );
    return cacheData;
  }

  async getWorkflowForm(workflow_id) {
    let formData = await this.helper.request(
      "v1",
      "/app/" + application_id + "/workflow/" + workflow_id + "/startform",
      {},
      "get"
    );
    return formData;
  }

  async getFormData(formId) {
    let formData = await this.helper.request(
      "v1",
      "/app/" + application_id + "/form/" + formId,
      {},
      "get"
    );
    return formData;
  }

  postSubmitCallback = (data) => {
    this.deleteCacheData().then((response) => {
      if (response.status == "success") {
        this.setState({
          formContent: undefined,
          formID: undefined,
          showMenuPage: true
        });
      }
    });
  };

  render() {
    return (
      <div style={{ height: "inherit", overflow: "auto" }}>
        {this.state.formContent ? (
          <div className="formContent">
            <FormRender
              postSubmitCallback={this.postSubmitCallback}
              core={this.core}
              page={this.state.cachePage}
              appId={application_id}
              workflowId={this.state.workflowId}
              formId={this.state.formID}
              content={this.state.formContent}
              data={this.state.cacheData}
            />
          </div>
        ) : this.state.showMenuPage ? (
          <LeftMenuTemplate
            core={this.core}
            params={this.params}
            proc={this.proc}
            appId={application_id}
          />
        ) : null}
        <div id="floater">
          <img src="./apps/HubDrive/img/poweredby.png"></img>
          <div className="helpText">
            <p>
              Support Email:
              <a
                target="_blank"
                href="mailto:support@eoxvantage.com?subject=HUB AutoDealer App - Help Desk&body=Hi, %0APlease help me with the following query with the new Auto Dealer Application%0A%0A"
              >
                support@eoxvantage.com
              </a>
            </p>
          </div>
        </div>
      </div>
    );
  }
}
export default Home;
