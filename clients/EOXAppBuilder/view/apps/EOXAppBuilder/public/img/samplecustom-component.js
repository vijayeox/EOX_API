import { React, Moment as moment } from "oxziongui";
//Please note exported component MUST be a functional component NOT class based.
export default function SampleCustomComponent(props) {
  return <SampleCustomComponent fileId={props.fileId} appId={props.appId} core={props.core} />;
}
class SampleCustomComponent extends React.Component {
  constructor(props) {
    super(props);
    this.helper = props.core.make("oxzion/restClient");
    this.appId = props.appId;
    this.state = {};
  }
  render() {
    return "Rendering from custom component";
  }
}
