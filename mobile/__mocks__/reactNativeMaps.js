const React = require('react');
const { View } = require('react-native');

const MapView = React.forwardRef((props, ref) => {
  React.useImperativeHandle(ref, () => ({
    animateToRegion: jest.fn(),
  }));

  return React.createElement(View, props, props.children);
});

const Marker = (props) => React.createElement(View, props);
const Polyline = (props) => React.createElement(View, props);

module.exports = MapView;
module.exports.default = MapView;
module.exports.Marker = Marker;
module.exports.Polyline = Polyline;
