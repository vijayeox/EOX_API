import React from "react";

class AdminPage extends React.Component {
  constructor(props) {
    super(props);
    this.columnConfig = [
      { title: "Firstname", field: "firstname", editable: false },
      { title: "Lastname", field: "lastname", editable: false },
      { title: "Producer Code", field: "producer_code", editable: true },
    ];
    this.OxzionGUIComponents = this.props.components;
    this.state = {
      userList: [],
      windowVisible: false,
    };
    this.core = this.props.args;
    this.helper = this.props.core.make("oxzion/restClient");
    this.loader = this.props.core.make("oxzion/splash");

    this.notif = React.createRef();
    this.parentGrid = React.createRef();
    this.child = React.createRef();
  }
  componentDidMount() {
    this.loader.show();
    this.getUserData().then((userListResponse) => {
      this.setState({ userList: userListResponse.data });
      this.loader.destroy();
    });
  }

  async getUserData() {
    let editedData = await this.helper.request(
      "v1",
      "/app/" + this.props.appId + "/command/delegate/UserListing",
      {},
      "get"
    );
    return editedData;
  }

  async inlineUpdate(item) {
    this.loader.show();
    let editedProducerCode = await this.helper.request(
      "v1",
      "/app/" + this.props.appId + "/delegate/UpdateProducerCode",
      item,
      "post"
    );
    if (editedProducerCode.status == "success") {
      this.getUserData().then((userListResponse) => {
        this.setState({ userList: userListResponse.data });
        this.loader.destroy();
      });
    } else {
      this.notif.current.notify(
        "Operation Failed",
        editedProducerCode.errors
          ? editedProducerCode.errors[0].message
          : editedProducerCode.message,
        "danger"
      );
      this.loader.destroy();
    }
  }
  render() {
    var columnConfig = this.columnConfig;
    return (
      <div className="customAdminPage">
        <div className="col-md-12 adminPageGrid">
          <React.Suspense fallback={<div>Loading...</div>}>
            <this.OxzionGUIComponents.OX_Grid
              ref={this.parentGrid}
              appId={this.props.appId}
              osjsCore={this.props.core}
              data={this.state.userList}
              expandable={true}
              resizable={true}
              columnConfig={columnConfig}
              inlineEdit={true}
              filterable={true}
              onDataStateChange={this.windowVisible}
              inlineActions={{
                update: (dataItem) => this.inlineUpdate(dataItem),
              }}
            />
          </React.Suspense>
        </div>
        <React.Suspense fallback={<div>Loading...</div>}>
          <this.OxzionGUIComponents.Notification ref={this.notif} />
        </React.Suspense>
      </div>
    );
  }
}

export default AdminPage;
