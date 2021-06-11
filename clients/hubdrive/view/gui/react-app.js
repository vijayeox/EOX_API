import React from "react";

class React_app extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    return (
      <div>
        <h3>Sample React app</h3>
        {JSON.stringify(this.props ,null, '\t')}
      </div>
    );
  }
}

export default React_app;
