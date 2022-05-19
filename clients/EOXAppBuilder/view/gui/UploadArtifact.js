import React, { Suspense } from "react";

class UploadArtifact extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      uploadFile: [],
    };
    if (this.props.entity == "app") {
      this.postURL = "/app/archive/upload";
      this.infoMessage =
        "Please verify if the zip archive contains valid application.yml file before proceeding with the import";
      this.fileExtension = ".zip";
    } else if (this.props.entity == "form") {
      this.postURL = "app/" + this.props.params.app_uuid + "/artifact/add/" + this.props.entity;
      this.infoMessage =
        "Please verify if the JSON file has a unique Form Name";
      this.fileExtension = ".json";
    } else if (this.props.entity == "workflow") {
      this.postURL =
        "app/" + this.props.params.app_uuid + "/artifact/add/" + this.props.entity;
      this.infoMessage =
        "Please verify if the BPMN file has a unique Workflow Name and has both start and end events";
      this.fileExtension = ".bpmn";
    } else if(this.props.entity == 'delegate' || this.props.entity == 'appupgrade'){
      this.postURL =
        "app/" + this.props.params.app_uuid + "/artifact/add/" + this.props.entity;
      this.infoMessage =
        "Please verify if the PHP file has a unique File Name";
      this.fileExtension = ".php";
    } else if(this.props.entity == 'template'){
      this.postURL =
        "app/" + this.props.params.app_uuid + "/artifact/add/" + this.props.entity;
      this.infoMessage =
        "Please verify if the Template file has a unique File Name";
      this.fileExtension = ".tpl";
    } else if(this.props.entity == 'transformer'){
      this.postURL =
        "app/" + this.props.params.app_uuid + "/artifact/add/" + this.props.entity;
      this.infoMessage =
        "Please verify if the Transformer file has a unique File Name";
      this.fileExtension = ".yml, .php";
    } else if(this.props.entity == 'migration'){
      this.postURL =
        "app/" + this.props.params.app_uuid + "/artifact/add/" + this.props.entity;
      this.infoMessage =
        "Please verify if the Migration file has a unique File Name";
      this.fileExtension = ".sql";
    }
    this.core = this.props.core;
    this.loader = this.core.make("oxzion/splash");
    this.notif = React.createRef();
  }

  async uploadFile() {
    let helper = this.core.make("oxzion/restClient");
    let response = await helper.request(
      "v1",
      this.postURL,
      { file: this.state.uploadFile[0].getRawFile() },
      "filepost"
    );
    return response;
  }

  stepDownPage() {
    let stepDownPage = new CustomEvent("stepDownPage", {
      detail: {},
      bubbles: true,
    });
    let handleGridRefresh = new CustomEvent("handleGridRefresh", {
      detail: {},
      bubbles: true,
    });
    if (document.getElementById("navigation_" + this.props.appId)) {
      document
        .getElementById("navigation_" + this.props.appId)
        .dispatchEvent(stepDownPage);
      document
        .getElementById("navigation_" + this.props.appId)
        .dispatchEvent(handleGridRefresh);
    }
  }

  onFileChange = (event) => {
    if(this.props.pattern){
      if(!new RegExp(this.props.pattern.regex).test(event.newState[0].name)){
        return this.notif.current.notify(
          `${this.props.pattern?.error || 'Invalid File Name'}`,
          "Please select a different file.",
          "danger"
        )
      }
    }
    let fileError = false;
    let validFiles = event.newState.filter((item) => {
      if (item.validationErrors) {
        if (item.validationErrors.length > 0) {
          fileError = true;
          return false;
        }
      } else {
        return true;
      }
    });

    if (validFiles) {
      this.setState({
        uploadFile: validFiles,
      });
    }
    fileError
      ? this.notif.current.notify(
          "Unsupported File",
          "Please select a valid file.",
          "danger"
        )
      : null;
  };

  render() {
    return (
      <div>
        <this.props.components.Notification ref={this.notif} />
        <div className="col-md-8">
          <h5
            className="alert alert-warning"
            role="alert"
            style={{ paddingBottom: "10px", width: "fit-content" }}
          >
            {this.infoMessage}
          </h5>
        </div>
          <div className="col-md-12">
            <this.props.components.KendoFileUploader.Upload
              accept={this.fileExtension}
              autoUpload={false}
              multiple={false}
              showActionButtons={false}
              files={this.state.uploadFile}
              onAdd={this.onFileChange}
              onRemove={this.onFileChange}
              restrictions={{
                allowedExtensions: [this.fileExtension],
                maxFileSize: 35000000,
              }}
            />
          </div>
        <div style={{ paddingTop: "10px" }}>
          <button
            type="button"
            className="btn btn-primary"
            disabled={this.state.uploadFile.length == 0}
            onClick={() => {
              this.loader.show();
              this.uploadFile().then((response) => {
                this.loader.destroy();
                if (response.status == "success") {
                  this.notif.current.notify(
                    "Upload Completed",
                    "You can now Edit or Deploy the application.",
                    "success"
                  );
                  if(this.props.postSubmitCallback){
                    this.props.postSubmitCallback?.()
                    return;
                  }
                  this.stepDownPage();
                } else {
                  this.notif.current.notify(
                    "Import Failed",
                    response.message,
                    "danger"
                  );
                }
              });
            }}
          >
            Upload
          </button>
        </div>
      </div>
    );
  }
}

export default UploadArtifact;
