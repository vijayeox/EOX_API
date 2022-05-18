import { React } from "oxziongui";
export default function InputFocuserComp() {
  return <InputFocuser />;
}
class InputFocuser extends React.Component {
  constructor(props) {
    super(props);
  }
  componentDidMount() {
    document
      .querySelector('div[class="41b77ef3-41db-4a52-8eb8-ba3ac9a9d771_pages"]')
      .addEventListener(
        "change",
        (e) => e.target.type === "text" && e.target.focus?.()
      );
  }
  render() {
    return null;
  }
}
