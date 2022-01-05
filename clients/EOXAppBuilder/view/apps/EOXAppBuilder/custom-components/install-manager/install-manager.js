import {
  OX_Grid,
  React,
  FormRender,
  KendoReactButtons,
  PopupDialog,
} from "oxziongui";
import * as OxzionGUIComponents from "oxziongui";
import UploadArtifact from "../../../../gui/UploadArtifact";
import businessForm from "./business-roles-form.json";
import "./install-manager.scss";
import form from "./metadata-form.json";
import accountForm from "./account-form.json";
import { InstallManagerService } from "./install-manager.service";
export default function InstallManager(props) {
  return (
    <AppInstaller
      core={props.core}
      appId={props.appId}
      parentPageData={props.parentPageData}
      data={props.data}
    />
  );
}
class AppInstaller extends React.Component {
  constructor(props) {
    super(props);
    this.core = props.core;
    this.loader = this.core.make("oxzion/splash");
    this.helper = this.core.make("oxzion/restClient");
    this.appId = props.appId;
    this.installOrUninstall = this.installOrUninstall.bind(this);
    this.state = {
      service: new InstallManagerService(
        props.parentPageData,
        this.props.data?.params?.type || "forInstall",
        this.core,
        this.appId
      ),
      orgReady: true,
    };
  }
  async installOrUninstall(org, update) {
    const isInstall = this.state.service.installationType === "forInstall" || update;
    const { status } = await this.helper.request(
      "v1",
      `app/${this.state.service.parentData.uuid}/${
        isInstall ? "install" : "uninstall"
      }/account/${(org || this.state.service.selectedOrganization).uuid}`,
      isInstall
        ? {
            ...this.state.service.parentData,
            start_options: this.state.service.metaData,
          }
        : this.state.service.parentData,
      "post"
    );
    const succeeded = status === "success";
    await PopupDialog.fire({
      icon: status,
      title: succeeded ? "Success" : "Failed",
      text: `${(isInstall && "Installation") || "Uninstallation"} ${
        succeeded ? "completed successfully" : "failed"
      }`,
    });
    if (succeeded) {
      this.setState(
        {
          service: this.state.service
            .setOrganization(false)
            .setMetadata(false)
            .setBusinessRoles(false),
        },
        () => this.updateType(this.state.service.installationType)
      );
    }
  }
  async updateType(type) {
    this.setState({
      service: this.state.service.setInstallationType(type),
      orgReady: false,
    });
    await new Promise((r) => setTimeout(r, 100));
    this.setState({
      orgReady: true,
    });
  }
  render() {
    return (
      <div className="install-manager">
        {!this.state.service.selectedOrganization &&
          !this.state.service.updateOrganization && (
            <div className="install-manager--tabs">
              <div
                className={
                  (this.state.service.installationType === "forInstall" &&
                    `install-manager--tabs_active`) ||
                  ""
                }
                onClick={() => this.updateType("forInstall")}
              >
                Install
              </div>
              <div
                className={
                  (this.state.service.installationType !== "forInstall" &&
                    `install-manager--tabs_active`) ||
                  ""
                }
                onClick={() => this.updateType("Installed")}
              >
                Installed
              </div>
              <div style={{ flex: 1, padding: 0, border: "unset" }}>
                <div
                  style={{ float: "right" }}
                  onClick={() => {
                    this.setState({
                      service: this.state.service.updateOrganizationData(true),
                    });
                  }}
                >
                  Create Account
                </div>
              </div>
            </div>
          )}
        <div className="install-manager_header">
          {this.state.service.selectedOrganization ||
          this.state.service.updateOrganization ? (
            <KendoReactButtons.Button
              className={"btn btn-primary install-manager-btn"}
              onClick={() => {
                this.setState({ service: this.state.service.goBack() });
              }}
            >
              <i className="fas fa-arrow-left"></i>
            </KendoReactButtons.Button>
          ) : null}
        </div>
        <div className="install-manager_content">
          {this.state.service.updateOrganization ? (
            <AAccount
              service={this.state.service}
              setService={this.setState.bind(this)}
            />
          ) : (
            <>
              {(!this.state.service.selectedOrganization && (
                <Organization
                  service={this.state.orgReady && this.state.service}
                  setService={this.setState.bind(this)}
                  installOrUninstall={this.installOrUninstall.bind(this)}
                />
              )) ||
                (!this.state.service.metaData && (
                  <Metadata
                    service={this.state.service}
                    setService={this.setState.bind(this)}
                  />
                )) ||
                (!this.state.service.businessRoles && (
                  <BusinessRoles
                    service={this.state.service}
                    setService={this.setState.bind(this)}
                  />
                )) || (
                  <Template
                    service={this.state.service}
                    setService={this.setState.bind(this)}
                  />
                )}
            </>
          )}
        </div>
        {
          this.state.service.metaData &&
            this.state.service.businessRoles && (
              <button
                className="install-manager_submit"
                onClick={() => this.installOrUninstall(null, true)}
              >
                {this.state.service.installationType === "forInstall"
                  ? "Install"
                  : "Update"}
              </button>
            )
          // ||
          // (this.state.service.installationType === "Installed" && (
          //   <button
          //     className="install-manager_submit"
          //     onClick={this.installOrUninstall}
          //   >
          //     Uninstall
          //   </button>
          // ))
        }
      </div>
    );
  }
}

class Template extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.core = this.service.core;
    this.api = this.core.make("oxzion/restClient");
    this.state = { gridLoading: false };
  }
  componentDidMount() {}
  async refreshGrid() {
    this.setState({ gridLoading: true });
    await new Promise((r) => setTimeout(r, 100));
    this.setState({ gridLoading: false });
  }
  async delete({ name }) {
    let { status, data } = await this.api.request(
      "v1",
      `app/${this.service.parentData.uuid}/artifact/delete/template/${name}.tpl`,
      this.service.parentData,
      "post"
    );
    status === "success" && this.refreshGrid();
  }
  render() {
    return (
      (!this.state.gridLoading && (
        <>
          <UploadArtifact
            components={OxzionGUIComponents}
            entity="template"
            postSubmitCallback={this.refreshGrid.bind(this)}
            core={this.core}
            appId={this.service.appId}
            params={{ app_uuid: this.service.parentData?.uuid }}
          />
          <OX_Grid
            osjsCore={this.core}
            data={`app/${this.service.parentData?.uuid}/artifact/list/template`}
            columnConfig={[{ title: "Template", field: "name" }]}
            actions={[
              {
                icon: "far fa-trash",
                name: "Delete",
                callback: this.delete.bind(this),
                rule: "true",
              },
            ]}
          />
        </>
      )) ||
      null
    );
  }
}
class AAccount extends React.Component {
  constructor(props) {
    super(props);
    console.log(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.core = this.service.core;
    this.api = this.core.make("oxzion/restClient");
    this.state = {
      data: null,
    };
    this.createOrg = this.createOrg.bind(this);
  }
  componentDidMount() {
    this.setState({ data: {} });
  }
  async createOrg(account) {
    try {
      let { status, data } = await this.api.request(
        "v1",
        `/account`,
        account,
        "post"
      );
      const succeeded = status === "success";
      if (succeeded) {
        await PopupDialog.fire({
          icon: "success",
          title: "Success",
          text: `Account created successfully`,
        });
        this.setService({ service: this.service.goBack() });
        return;
      }
      await PopupDialog.fire({
        icon: "error",
        title: "Failed",
        text: `Failed to create account`,
      });
      this.setState({ data: false });
      await new Promise((r) => setTimeout(r, 100));
      this.setState({ data: account });
    } catch (e) {
      console.error(e);
    }
  }
  render() {
    return (
      (this.state.data && (
        <FormRender
          content={accountForm}
          core={this.core}
          postSubmitCallback={this.createOrg}
          updateFormData={true}
          data={this.state.data}
          core={this.props.service.core}
          updateFormData={true}
        />
      )) ||
      null
    );
  }
}
class Metadata extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.core = this.service.core;
    this.api = this.core.make("oxzion/restClient");
    this.state = {
      data: null,
    };
  }
  async componentDidMount() {
    try {
      let { status, data } = await this.api.request(
        "v1",
        `app/${this.service.parentData.uuid}/account/${this.service.selectedOrganization.uuid}/appProperties`,
        {},
        "get"
      );
      data = JSON.parse(data[0]["start_options"]);
      if (status === "success") {
        this.setState({
          data: {
            ...data,
            description: data.description.en_EN,
            title: data.title.en_EN,
          },
        });
      }
    } catch (e) {
      this.setState({
        data: {},
      });
      console.error(e);
    }
  }
  render() {
    return this.state.data ? (
      <FormRender
        content={form}
        core={this.core}
        postSubmitCallback={(...args) => {
          let newMetadata = args[0];
          newMetadata["description"] = {
            en_EN: newMetadata.description ? `${newMetadata.description}` : "",
          };
          newMetadata["title"] = {
            en_EN: newMetadata.title
              ? `${newMetadata.title}`
              : newMetadata.title,
          };
          this.setService({ service: this.service.setMetadata(newMetadata) });
        }}
        data={this.state.data}
        updateFormData={true}
      />
    ) : null;
  }
}
class Organization extends React.Component {
  constructor(props) {
    super(props);
    // this.service = this.props.service;
    // this.setService = this.props.setService;
  }
  render() {
    return (
      (this.props.service && (
        <OX_Grid
          osjsCore={this.props.service.core}
          data={`app/${this.props.service.parentData.uuid}/getAccounts/${this.props.service.installationType}?filter=[{%22take%22:1000,%22skip%22:0}]`}
          columnConfig={[{ title: "Account", field: "name" }]}
          actions={
            this.props.service.installationType === "forInstall"
              ? [
                  {
                    icon: "far fa-download",
                    name:
                      (this.props.service.installationType === "forInstall" &&
                        "Install") ||
                      "Uninstall",
                    callback: (data) => {
                      this.props.setService({
                        service: this.props.service.setOrganization(data),
                      });
                    },
                    rule: "true",
                    defaultAction: true,
                  },
                ]
              : [
                  {
                    icon: "far fa-download",
                    name:
                      (this.props.service.installationType === "forInstall" &&
                        "Install") ||
                      "Uninstall",
                    callback: (data) => {
                      // this.props.setService({
                      //   service: this.props.service.setOrganization(data),
                      // });
                      this.props?.installOrUninstall(data);
                    },
                    rule: "true",
                    defaultAction: true,
                  },
                  {
                    icon: "far fa-pencil",
                    name: "Edit",
                    callback: (data) => {
                      this.props.setService({
                        service: this.props.service.setOrganization(data),
                      });
                    },
                    rule: "true",
                  },
                ]
          }
        />
      )) ||
      null
    );
  }
}

class BusinessRoles extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.core = this.service.core;
    this.api = this.core.make("oxzion/restClient");
    this.state = {
      data: null,
    };
  }
  async componentDidMount() {
    try {
      const [entity,roles] = await Promise.all([this.api.request(
        "v1",
        `/app/${this.service.parentData?.uuid}/entity`,
        {},
        "get"
      ),this.api.request(
        "v1",
        `/app/${this.service.parentData?.uuid}/appBusinessRoles`,
        {},
        "get"
      )])
      // data = JSON.parse(data[0]["start_options"]);
      // if (status === "success") {
        this.setState({
          data : {
            businessRole : roles.data,
            entity : entity.data,
          },
        },console.log);
      // }
    } catch (e) {
      this.setState({
        data: {},
      });
      console.error(e);
    }
  }
  render() {
    return this.state.data ? (
      <FormRender
        content={businessForm}
        core={this.core}
        postSubmitCallback={(data) => {
          this.setService({ service: this.service.setBusinessRoles(data) });
        }}
        // dataUrl={
        //   this.service.businessRoles
        //     ? null
        //     : `/app/${this.service.parentData?.uuid}/appBusinessRoles`
        // }
        data={this.state.data}
        updateFormData={true}
      />
    ) : null;
  }
}
