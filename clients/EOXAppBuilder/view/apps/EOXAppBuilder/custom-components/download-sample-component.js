import { React } from "oxziongui";
export default function SampleComponentDownload(props) {
  return (
    <SampleDownloadComponent
      core={props.core}
      appId={props.appId}
      parentPageData={props.parentPageData}
      data={props.data}
    />
  );
}
class SampleDownloadComponent extends React.Component {
  constructor(props) {
    super(props);
  }
  render() {
    return (
      <a
        style={{
          background: "#007bff",
          color: "#FFF",
          padding: " 0.6rem",
          borderRadius: "6px",
          fontSize: "12px",
          position: "absolute",
          top: ".5rem",
          backgroundColor: "#e4e7eb",
          color: "#000",
          right: "3.75rem",
        }}
        download={"sample-component.js"}
        href={"/apps/EOXAppBuilder/appstudio/samplecomponent"}
        target="_blank"
        rel="noopener noreferrer"
      >
        Click here to download sample component
      </a>
    );
  }
}
