import { React } from "oxziongui";
/**
 * Please note exported component MUST be a functional component NOT class based.
 * here exported function *SampleCustomComponent* must be in index.js object literal of EOXApplication childrenComponents props
 * example : <EOXApplication childrenComponents={{ yourReactId : SampleCustomComponent }}/> along with this SampleCustomComponent must also be imported intially in EditIndex tab.
 * *yourReactId* needs to reside in reactId of ReactComponent PageContent in AppStudio
 */
export default function SampleCustomComponent(props) {
  return <YourSampleComponent fileId={props.fileId} appId={props.appId} core={props.core} />;
}
class YourSampleComponent extends React.Component {
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
